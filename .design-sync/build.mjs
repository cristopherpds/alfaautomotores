// Pre-build for the design-sync converter (cfg.buildCmd). Run from the repo root:
//   node .design-sync/build.mjs
// 1. Emits .d.ts for .design-sync/pkg/entry.ts (the converter's prop contracts).
// 2. Compiles .design-sync/ds.css with Tailwind v4 (the converter's cfg.cssEntry).
// Both land in .design-sync/pkg/build/ (gitignored, regenerated every sync).
import { execFileSync } from 'node:child_process';
import { mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { dirname, relative, resolve, sep } from 'node:path';
import { compile, optimize } from '@tailwindcss/node';
import { Scanner } from '@tailwindcss/oxide';

const buildDir = resolve('.design-sync/pkg/build');
rmSync(buildDir, { recursive: true, force: true });

try {
    execFileSync(process.execPath, [resolve('node_modules/typescript/bin/tsc'), '-p', '.design-sync/tsconfig.types.json'], { stdio: 'pipe' });
} catch (error) {
    // Type errors elsewhere in the app don't block declaration emit; surface them anyway.
    console.error(`tsc reported errors (declarations still emitted):\n${String(error.stdout).slice(0, 2000)}`);
}

// The converter's type checker has no tsconfig paths: point `@/…` at the emitted tree.
const typesRoot = resolve(buildDir, 'types');
const appTypes = resolve(typesRoot, 'resources/js');
for (const file of readdirSync(typesRoot, { recursive: true })) {
    if (!file.endsWith('.d.ts')) {
        continue;
    }
    const path = resolve(typesRoot, file);
    const rewritten = readFileSync(path, 'utf8').replace(/(['"])@\/([^'"]+)\1/g, (_, quote, target) => {
        const rel = relative(dirname(path), resolve(appTypes, target)).split(sep).join('/');
        return `${quote}${rel.startsWith('.') ? rel : `./${rel}`}${quote}`;
    });
    writeFileSync(path, rewritten);
}

// brand.tsx points at /assets/logo-alfa.jpg, a Laravel public path that doesn't exist in
// Claude Design. Ship the real component with the logo inlined, the way a bundler would.
const brandSource = readFileSync(resolve('resources/js/components/alfa/brand.tsx'), 'utf8');
const logoLine = "const LOGO_SRC = '/assets/logo-alfa.jpg';";
if (!brandSource.includes(logoLine)) {
    throw new Error(`brand.tsx no longer contains "${logoLine}" - update .design-sync/build.mjs`);
}
const logoDataUri = `data:image/jpeg;base64,${readFileSync(resolve('public/assets/logo-alfa.jpg')).toString('base64')}`;
writeFileSync(resolve(buildDir, 'brand.tsx'), brandSource.replace(logoLine, `const LOGO_SRC = '${logoDataUri}';`));

const input = resolve('.design-sync/ds.css');
const output = resolve(buildDir, 'ds.compiled.css');
const compiler = await compile(readFileSync(input, 'utf8'), { base: dirname(input), onDependency: () => {} });
const sources = [
    { base: resolve('resources/js'), pattern: '**/*', negated: false },
    { base: resolve('.design-sync/previews'), pattern: '**/*', negated: false },
    ...compiler.sources,
];
const candidates = new Scanner({ sources }).scan();
const css = optimize(compiler.build(candidates), { minify: false }).code;

mkdirSync(buildDir, { recursive: true });
writeFileSync(output, css);
console.error(`ds.css -> ${output} (${candidates.length} candidates, ${(css.length / 1024).toFixed(0)} KB)`);
