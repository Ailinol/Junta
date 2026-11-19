import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { spawn } from 'node:child_process';

const root = path.dirname(fileURLToPath(import.meta.url));
const database = path.join(
    root,
    'storage',
    'app',
    'purchase-demonstration.sqlite',
);
const origin = 'http://127.0.0.1:8786';
const env = {
    ...process.env,
    APP_ENV: 'local',
    APP_URL: origin,
    APP_DEBUG: 'false',
    APP_CONFIG_CACHE: path.join(
        root,
        'storage',
        'app',
        'purchase-demonstration-config.php',
    ),
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_URL: '',
    SESSION_DRIVER: 'database',
    SESSION_CONNECTION: 'sqlite',
    SESSION_STORE: '',
    SESSION_COOKIE: 'junta_purchase_demonstration',
    SESSION_DOMAIN: '',
    SESSION_SECURE_COOKIE: 'false',
    CACHE_STORE: 'database',
    DB_CACHE_CONNECTION: 'sqlite',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    JEV_ENABLED: 'false',
    PURCHASE_PAYMENT_PROVIDER: 'test',
    PURCHASE_TEST_ENABLED: 'true',
};

if (fs.existsSync(env.APP_CONFIG_CACHE)) {
    throw new Error(
        'Existe configuração em cache exclusiva da demonstração. Remova esse ficheiro antes de continuar.',
    );
}
const [action = 'estado', ...options] = process.argv.slice(2);
const actions = { preparar: 'reset', abrir: 'open', estado: 'status' };
if (Object.hasOwn(actions, action)) {
    const child = spawn(
        'php',
        [
            'artisan',
            'junta:demo-purchases',
            actions[action],
            ...options,
            '--no-interaction',
        ],
        { cwd: root, env, stdio: 'inherit' },
    );
    child.on('error', (error) => {
        console.error(error.message);
        process.exitCode = 1;
    });
    child.on('exit', (code) => {
        process.exitCode = code ?? 1;
    });
} else if (action === 'servir') {
    if (!fs.existsSync(database))
        throw new Error(
            'Execute primeiro: node demonstracao-junta.mjs preparar',
        );
    if (
        !fs.existsSync(path.join(root, 'public', 'build', 'manifest.json')) ||
        fs.existsSync(path.join(root, 'public', 'hot'))
    ) {
        throw new Error(
            'Termine o Vite de desenvolvimento e execute npm.cmd run build antes de servir a demonstração.',
        );
    }
    const log = fs.openSync(
        path.join(root, 'storage', 'logs', 'purchase-demonstration-server.log'),
        'a',
    );
    const backend = spawn(
        'php',
        [
            '-S',
            '127.0.0.1:8787',
            '-t',
            path.join(root, 'public'),
            path.join(
                root,
                'vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php',
            ),
        ],
        {
            cwd: path.join(root, 'public'),
            env,
            stdio: ['ignore', log, log],
            windowsHide: true,
        },
    );
    const publicRoot = fs.realpathSync(path.join(root, 'public'));
    const mime = {
        '.js': 'text/javascript',
        '.css': 'text/css',
        '.woff': 'font/woff',
        '.woff2': 'font/woff2',
        '.ttf': 'font/ttf',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.jpeg': 'image/jpeg',
        '.webp': 'image/webp',
        '.avif': 'image/avif',
        '.svg': 'image/svg+xml',
        '.ico': 'image/x-icon',
    };
    const server = http.createServer((request, response) => {
        const url = new URL(request.url, origin);
        let file;
        try {
            file = path.resolve(
                publicRoot,
                '.' + decodeURIComponent(url.pathname),
            );
        } catch {
            response.writeHead(400).end();
            return;
        }
        if (
            file.startsWith(publicRoot + path.sep) &&
            Object.hasOwn(mime, path.extname(file)) &&
            fs.existsSync(file) &&
            fs.statSync(file).isFile()
        ) {
            const resolved = fs.realpathSync(file);
            if (!resolved.startsWith(publicRoot + path.sep)) {
                response.writeHead(403).end();
                return;
            }
            response.writeHead(200, {
                'Content-Type': mime[path.extname(file)],
            });
            if (request.method === 'HEAD') response.end();
            else fs.createReadStream(file).pipe(response);
            return;
        }
        const upstream = http.request(
            {
                hostname: '127.0.0.1',
                port: 8787,
                path: request.url,
                method: request.method,
                headers: request.headers,
            },
            (result) => {
                response.writeHead(result.statusCode, result.headers);
                result.pipe(response);
            },
        );
        upstream.on('error', () => {
            if (!response.headersSent) response.writeHead(502);
            response.end(
                'Servidor Laravel indisponivel. Consulte storage/logs/purchase-demonstration-server.log.',
            );
        });
        request.pipe(upstream);
    });
    const stop = () => {
        server.close();
        backend.kill();
        fs.closeSync(log);
    };
    process.once('SIGINT', () => {
        stop();
        process.exit(0);
    });
    process.once('SIGTERM', () => {
        stop();
        process.exit(0);
    });
    backend.once('error', (error) => {
        console.error(error.message);
        stop();
        process.exitCode = 1;
    });
    backend.once('exit', (code) => {
        server.close();
        if (code) process.exitCode = code;
    });
    server.once('error', (error) => {
        console.error(error.message);
        backend.kill();
        process.exitCode = 1;
    });
    server.listen(8786, '127.0.0.1', () =>
        console.log(
            `Demonstração: ${origin} — base exclusiva: ${database}. Ctrl+C para terminar.`,
        ),
    );
} else {
    console.error(
        'Use preparar | abrir --scenario=success|refund --minutes=15 | estado | servir',
    );
    process.exitCode = 1;
}
