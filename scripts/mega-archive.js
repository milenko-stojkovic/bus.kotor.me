/**
 * Server-side MEGA helper for Bus Kotor (upload/download under one base folder).
 * Credentials: MEGA_EMAIL, MEGA_PASSWORD, MEGA_BASE_FOLDER, MEGA_USER_AGENT (env).
 * stdin: JSON payload. stdout: single-line JSON result.
 */
import { createReadStream, createWriteStream, existsSync, mkdirSync } from 'fs';
import { statSync } from 'fs';
import { dirname } from 'path';
import { Storage } from 'megajs';

const DEFAULT_MEGA_USER_AGENT = 'BusKotorArchive/1.0';

function megaUserAgent() {
    const v = (process.env.MEGA_USER_AGENT || '').trim();
    return v !== '' ? v : DEFAULT_MEGA_USER_AGENT;
}

function readStdin() {
    return new Promise((resolve) => {
        let data = '';
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => {
            data += chunk;
        });
        process.stdin.on('end', () => resolve(data));
    });
}

function sendResult(obj) {
    process.stdout.write(JSON.stringify(obj));
}

async function getStorage() {
    const email = process.env.MEGA_EMAIL || '';
    const password = process.env.MEGA_PASSWORD || '';
    if (!email || !password) {
        throw new Error('MEGA_EMAIL and MEGA_PASSWORD are required');
    }
    const storage = await new Storage({ email, password, userAgent: megaUserAgent() }).ready;
    return storage;
}

async function ensureBaseFolder(storage) {
    const name = process.env.MEGA_BASE_FOLDER || 'bus.kotor';
    let folder = storage.root.children.find((c) => c.directory && c.name === name);
    if (!folder) {
        folder = await storage.mkdir(name);
    }
    return { folder, name };
}

async function ensureFolderPath(baseFolder, relativePath) {
    const parts = String(relativePath || '')
        .split('/')
        .map((p) => p.trim())
        .filter(Boolean);
    let current = baseFolder;
    for (const part of parts) {
        let child = (current.children || []).find((c) => c.directory && c.name === part);
        if (!child) {
            child = await current.mkdir(part);
        }
        current = child;
    }
    return current;
}

async function upload(payload) {
    const localPath = payload.localPath;
    const targetName = payload.targetName;
    const targetRelativeDir = payload.targetRelativeDir || '';
    if (!localPath || !targetName) {
        return { ok: false, error: 'localPath and targetName required' };
    }
    if (!existsSync(localPath)) {
        return { ok: false, error: 'local file missing' };
    }
    const storage = await getStorage();
    const { folder, name: baseName } = await ensureBaseFolder(storage);
    const targetFolder =
        targetRelativeDir && String(targetRelativeDir).trim() !== ''
            ? await ensureFolderPath(folder, targetRelativeDir)
            : folder;
    const st = statSync(localPath);
    const stream = createReadStream(localPath);
    const uploadTask = targetFolder.upload({ name: targetName, size: st.size }, stream);
    const file = await uploadTask.complete;
    const nodeId =
        file?.node?.hash ||
        file?.node?.h ||
        file?.node?.k ||
        (typeof file?.node === 'string' ? file.node : null) ||
        null;
    const dirPart =
        targetRelativeDir && String(targetRelativeDir).trim() !== ''
            ? String(targetRelativeDir).replace(/^\/+|\/+$/g, '') + '/'
            : '';
    const megaPath = `${baseName}/${dirPart}${targetName}`;
    return {
        ok: true,
        mega_node_id: nodeId != null ? String(nodeId) : null,
        mega_path: megaPath,
    };
}

async function diagnose() {
    const emailPresent = Boolean((process.env.MEGA_EMAIL || '').trim());
    const passwordPresent = Boolean((process.env.MEGA_PASSWORD || '').trim());
    const baseFolder = process.env.MEGA_BASE_FOLDER || 'bus.kotor';
    const userAgent = megaUserAgent();
    const rootChildrenSample = [];

    if (!emailPresent || !passwordPresent) {
        return {
            ok: false,
            email_present: emailPresent,
            password_present: passwordPresent,
            base_folder: baseFolder,
            user_agent: userAgent,
            node_version: process.version,
            login_ok: false,
            folder_found: false,
            root_children_sample: [],
            error: 'MEGA_EMAIL or MEGA_PASSWORD missing in environment',
        };
    }

    try {
        const storage = await getStorage();
        const children = storage.root.children || [];
        for (let i = 0; i < children.length && rootChildrenSample.length < 10; i++) {
            const c = children[i];
            const tag = c.directory ? `dir:${c.name}` : `file:${c.name}`;
            rootChildrenSample.push(tag);
        }
        const folder = children.find((c) => c.directory && c.name === baseFolder);
        const folderFound = Boolean(folder);
        return {
            ok: folderFound,
            email_present: true,
            password_present: true,
            base_folder: baseFolder,
            user_agent: userAgent,
            node_version: process.version,
            login_ok: true,
            folder_found: folderFound,
            root_children_sample: rootChildrenSample,
            error: folderFound
                ? ''
                : `Base folder "${baseFolder}" not found under MEGA root (not created during diagnose).`,
        };
    } catch (e) {
        return {
            ok: false,
            email_present: true,
            password_present: true,
            base_folder: baseFolder,
            user_agent: userAgent,
            node_version: process.version,
            login_ok: false,
            folder_found: false,
            root_children_sample: rootChildrenSample,
            error: e && e.message ? String(e.message) : 'login failed',
        };
    }
}

async function download(payload) {
    const megaPath = payload.megaPath;
    const destAbsolutePath = payload.destAbsolutePath;
    const generatedFileName = payload.generatedFileName || payload.targetName;
    if (!destAbsolutePath) {
        return { ok: false, error: 'destAbsolutePath required' };
    }
    const storage = await getStorage();
    let file = null;
    if (megaPath) {
        file = storage.navigate(megaPath);
    }
    // Fallback: locate by file name under base folder (mega_path missing or stale)
    if ((!file || file.directory) && generatedFileName) {
        const { folder } = await ensureBaseFolder(storage);
        file = folder.children?.find((c) => !c.directory && c.name === generatedFileName) || null;
    }
    if (!file || file.directory) {
        return {
            ok: false,
            error: 'MEGA file not found: ' + (megaPath || generatedFileName || '(no path)'),
        };
    }
    mkdirSync(dirname(destAbsolutePath), { recursive: true });
    // megajs: download() returns a Readable. The optional callback receives (err, Buffer), not a stream — do not use .pipe on the callback argument.
    await new Promise((resolve, reject) => {
        const ws = createWriteStream(destAbsolutePath);
        const rs = file.download({});
        rs.on('error', reject);
        ws.on('error', reject);
        ws.on('finish', resolve);
        rs.pipe(ws);
    });
    const resolvedPath =
        megaPath ||
        `${process.env.MEGA_BASE_FOLDER || 'bus.kotor'}/${file.name || generatedFileName || ''}`;
    return { ok: true, mega_path: resolvedPath };
}

async function main() {
    const action = process.argv[2];
    const raw = await readStdin();
    let payload = {};
    try {
        payload = raw ? JSON.parse(raw) : {};
    } catch {
        sendResult({ ok: false, error: 'invalid stdin JSON' });
        process.exit(1);
        return;
    }
    try {
        let out;
        if (action === 'upload') {
            out = await upload(payload);
        } else if (action === 'download') {
            out = await download(payload);
        } else if (action === 'diagnose') {
            out = await diagnose();
        } else {
            out = { ok: false, error: 'unknown action: ' + String(action) };
        }
        sendResult(out);
        process.exit(out.ok ? 0 : 1);
    } catch (e) {
        sendResult({ ok: false, error: e && e.message ? String(e.message) : 'error' });
        process.exit(1);
    }
}

main();
