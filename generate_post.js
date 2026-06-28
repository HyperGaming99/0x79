#!/usr/bin/env node
const fs = require('fs');
const path = require('path');

// Default configurations
const GEMINI_MODEL = "gemini-2.5-flash"; // Alternatives: gemini-2.5-pro, gemini-1.5-pro, gemini-1.5-flash
const IMAGEN_MODEL = "imagen-4.0-generate-001";

// ---------------------------------------------------------
// ENV LOADER
// ---------------------------------------------------------
function loadEnv() {
    const envPath = path.join(__dirname, '.env');
    if (fs.existsSync(envPath)) {
        const content = fs.readFileSync(envPath, 'utf-8');
        const lines = content.split('\n');
        for (const line of lines) {
            const trimmed = line.trim();
            if (trimmed && !trimmed.startsWith('#') && trimmed.includes('=')) {
                const [key, val] = trimmed.split('=', 2);
                process.env[key.strip ? key.strip() : key.trim()] = val.strip ? val.strip() : val.trim();
            }
        }
    }
}

loadEnv();

const SUPABASE_URL = process.env.SUPABASE_URL;
const SUPABASE_KEY = process.env.SUPABASE_KEY;
const FILE_UPLOAD_BUCKET = process.env.FILE_UPLOAD_BUCKET || "images";
const GEMINI_API_KEY = process.env.GEMINI_API_KEY;

// Check credentials
if (!SUPABASE_URL || !SUPABASE_KEY) {
    console.error("Error: SUPABASE_URL or SUPABASE_KEY missing in .env");
    process.exit(1);
}

if (!GEMINI_API_KEY || GEMINI_API_KEY === "dein_gemini_api_key_hier") {
    console.error("Error: Bitte setze deinen 'GEMINI_API_KEY' in der .env Datei.");
    process.exit(1);
}

// Topic ideas if no custom topic is provided
const TOPICS = [
    "Aktuelle Phishing-Kampagnen auf deutsche Unternehmen",
    "Warum Zwei-Faktor-Authentisierung (2FA) gehackt werden kann und wie man sich schützt",
    "Die Bedrohung durch Social Engineering und Spear-Phishing im Homeoffice",
    "Wie Ransomware-Banden vorgehen und was nach einem Angriff zu tun ist",
    "Passwort-Sicherheit: Warum '123456' noch immer gewinnt und wie Passwortmanager helfen",
    "Die Gefahren von öffentlichem WLAN und wie man ein VPN richtig nutzt",
    "Künstliche Intelligenz im Cybercrime: Deepfakes und automatisierte Angriffe",
    "Sicherheits-Check für Router und Smarthome-Geräte zu Hause"
];

async function generateBlogContent(topic) {
    console.log(`-> Generiere Blogbeitrag zum Thema: '${topic}' mit ${GEMINI_MODEL}...`);
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${GEMINI_MODEL}:generateContent?key=${GEMINI_API_KEY}`;
    
    const prompt = `
    Schreibe einen professionellen, informativen Blogbeitrag auf Deutsch über das Thema: "${topic}".
    Der Beitrag richtet sich an technisch interessierte Leser sowie normale Anwender. Erkläre Bedrohungen verständlich und gib konkrete Tipps zur Abhilfe.
    
    Antworte AUSSCHLIESSLICH mit einem validen JSON-Objekt. Verwende genau diese Schlüssel:
    1. "title": Ein kurzer, packender Titel für den Blogbeitrag.
    2. "description": Der vollständige Blogbeitrag in schönem Markdown-Format (mit Überschriften, Listen und Absätzen). Mindestens 300 Wörter.
    3. "image_prompt": Ein detaillierter, englischer Prompt für einen KI-Bildgenerator (wie Imagen oder DALL-E) passend zu diesem Beitrag. Beschreibe eine moderne, visuelle Metapher (z.B. "Cybersecurity glowing network locks, dark background, neon blue and purple lights, high-tech, 3D render, 16:9").
    
    Gib kein Markdown-Codeblock-Ummantelung (wie \`\`\`json) um die Antwort herum aus. Gib nur das rohe JSON aus.
    `;

    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            contents: [{ parts: [{ text: prompt }] }],
            generationConfig: { responseMimeType: 'application/json' }
        })
    });

    if (!response.ok) {
        const errText = await response.text();
        console.error(`Gemini API Fehler (${response.status}): ${errText}`);
        process.exit(1);
    }

    const data = await response.json();
    try {
        const textContent = data.candidates[0].content.parts[0].text;
        return JSON.parse(textContent.trim());
    } catch (e) {
        console.error("Fehler beim Parsen des Gemini-Ergebnisses:", e);
        console.error("Antwortdaten:", JSON.stringify(data));
        process.exit(1);
    }
}

async function generateImage(imagePrompt) {
    console.log(`-> Generiere Vorschaubild mit ${IMAGEN_MODEL}...`);
    const url = `https://generativelanguage.googleapis.com/v1beta/models/${IMAGEN_MODEL}:predict?key=${GEMINI_API_KEY}`;
    
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            instances: [
                {
                    prompt: imagePrompt
                }
            ],
            parameters: {
                sampleCount: 1,
                outputMimeType: "image/jpeg",
                aspectRatio: "16:9"
            }
        })
    });

    if (!response.ok) {
        const errText = await response.text();
        console.error(`Imagen API Fehler (${response.status}): ${errText}`);
        console.log("Falle zurück auf einen leeren Bildpfad...");
        return null;
    }

    try {
        const data = await response.json();
        const base64Data = data.predictions[0].bytesBase64Encoded;
        return Buffer.from(base64Data, 'base64');
    } catch (e) {
        console.error("Fehler beim Extrahieren der Bilddaten:", e);
        return null;
    }
}

async function uploadImageToSupabase(imageBuffer) {
    if (!imageBuffer) return null;

    const timestamp = Math.floor(Date.now() / 1000);
    const filename = `blog_${timestamp}.jpg`;
    console.log(`-> Lade Bild hoch in Supabase Storage (${FILE_UPLOAD_BUCKET}/${filename})...`);

    const uploadUrl = `${SUPABASE_URL.replace(/\/$/, '')}/storage/v1/object/${FILE_UPLOAD_BUCKET}/${filename}`;

    const response = await fetch(uploadUrl, {
        method: 'POST',
        headers: {
            'apikey': SUPABASE_KEY,
            'Authorization': `Bearer ${SUPABASE_KEY}`,
            'Content-Type': 'image/jpeg'
        },
        body: imageBuffer
    });

    if (!response.ok) {
        const errText = await response.text();
        console.error(`Supabase Storage Upload Fehler (${response.status}): ${errText}`);
        return null;
    }

    return `${SUPABASE_URL.replace(/\/$/, '')}/storage/v1/object/public/${FILE_UPLOAD_BUCKET}/${filename}`;
}

async function publishPost(title, description, imageUrl) {
    console.log("-> Erstelle Post in der Supabase-Datenbank...");
    const dbUrl = `${SUPABASE_URL.replace(/\/$/, '')}/rest/v1/posts`;

    const response = await fetch(dbUrl, {
        method: 'POST',
        headers: {
            'apikey': SUPABASE_KEY,
            'Authorization': `Bearer ${SUPABASE_KEY}`,
            'Content-Type': 'application/json',
            'Prefer': 'return=representation'
        },
        body: JSON.stringify({
            title: title,
            description: description,
            image: imageUrl,
            pub_date: Math.floor(Date.now() / 1000)
        })
    });

    if (!response.ok) {
        const errText = await response.text();
        console.error(`Fehler beim Erstellen des Beitrags (${response.status}): ${errText}`);
        process.exit(1);
    }

    try {
        const postData = await response.json();
        return Array.isArray(postData) ? postData[0].id : postData.id;
    } catch (e) {
        return null;
    }
}

async function main() {
    const topic = process.argv[2] || TOPICS[Math.floor(random() * TOPICS.length)];
    
    try {
        // 1. Text
        const postData = await generateBlogContent(topic);
        const title = postData.title || topic;
        const description = postData.description || "";
        const imagePrompt = postData.image_prompt || `Tech illustration for: ${topic}`;

        // 2. Image
        const imageBuffer = await generateImage(imagePrompt);

        // 3. Upload
        const imageUrl = await uploadImageToSupabase(imageBuffer);

        // 4. Save
        const postId = await publishPost(title, description, imageUrl);

        console.log("\n" + "=".repeat(50));
        console.log(" ERFOLGREICH VERÖFFENTLICHT!");
        console.log("=".repeat(50));
        console.log(`Titel:      ${title}`);
        if (postId) {
            console.log(`Post-ID:    ${postId}`);
            console.log(`URL:        https://0x79.one/post/${postId}`);
        }
        if (imageUrl) {
            console.log(`Bild-URL:   ${imageUrl}`);
        }
        console.log("=".repeat(50) + "\n");
    } catch (err) {
        console.error("Unerwarteter Fehler im Ablauf:", err);
    }
}

function random() {
    return Math.random();
}

main();
