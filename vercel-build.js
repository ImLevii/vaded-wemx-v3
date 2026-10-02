import { cp, mkdir, rm } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const projectRoot = path.dirname(fileURLToPath(import.meta.url));
const outputDirectory = path.join(projectRoot, '.vercel/static');
const publicFiles = ['build', 'icons', 'favicon.ico', 'robots.txt', 'assets/common'];

await rm(outputDirectory, { recursive: true, force: true });
await mkdir(outputDirectory, { recursive: true });

const isStaticAsset = (source) => {
    const name = path.basename(source);

    return !name.startsWith('.') && !/\.(php\d*|phtml|phar)$/i.test(name);
};

for (const file of publicFiles) {
    await cp(path.join(projectRoot, 'public', file), path.join(outputDirectory, file), {
        recursive: true,
        filter: isStaticAsset,
    });
}

for (const [source, destination, theme] of [
    ['client_area', 'clientarea', process.env.APP_THEME || 'default'],
    ['admin_area', 'adminarea', process.env.APP_ADMIN_THEME || 'default'],
]) {
    if (!/^[a-zA-Z0-9_-]+$/.test(theme)) {
        throw new Error(`Invalid theme directory: ${theme}`);
    }

    await cp(
        path.join(projectRoot, 'resources', source, theme, 'assets'),
        path.join(outputDirectory, 'assets', destination, theme),
        { recursive: true, filter: isStaticAsset },
    );
}

console.log('Vercel static assets prepared.');
