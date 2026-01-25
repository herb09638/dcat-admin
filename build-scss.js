import * as sass from 'sass';
import { writeFileSync, mkdirSync, copyFileSync, cpSync, existsSync } from 'fs';
import { dirname, resolve } from 'path';
import { glob } from 'glob';

const isProduction = process.env.NODE_ENV === 'production';
const distPath = isProduction ? 'resources/dist' : 'resources/pre-dist';

console.log(`Building SCSS for ${isProduction ? 'production' : 'development'}...`);
console.log(`Output directory: ${distPath}`);

// Ensure directories exist
mkdirSync(`${distPath}/adminlte`, { recursive: true });
mkdirSync(`${distPath}/dcat/css`, { recursive: true });
mkdirSync(`${distPath}/dcat/extra`, { recursive: true });

// Compile AdminLTE SCSS
console.log('Compiling AdminLTE.scss...');
try {
    const adminlteResult = sass.compile('resources/assets/adminlte/scss/AdminLTE.scss', {
        style: isProduction ? 'compressed' : 'expanded',
        sourceMap: true,
        loadPaths: ['node_modules'],
    });
    writeFileSync(`${distPath}/adminlte/adminlte.css`, adminlteResult.css);
    console.log('  -> adminlte/adminlte.css');
} catch (error) {
    console.error('Error compiling AdminLTE.scss:', error.message);
}

// Compile Dcat App SCSS
console.log('Compiling dcat-app.scss...');
try {
    const dcatResult = sass.compile('resources/assets/dcat/sass/dcat-app.scss', {
        style: isProduction ? 'compressed' : 'expanded',
        sourceMap: true,
        loadPaths: ['node_modules'],
    });
    writeFileSync(`${distPath}/dcat/css/dcat-app.css`, dcatResult.css);
    console.log('  -> dcat/css/dcat-app.css');
} catch (error) {
    console.error('Error compiling dcat-app.scss:', error.message);
}

// Copy static CSS files
console.log('Copying static CSS files...');
if (existsSync('resources/assets/dcat/sass/nunito.css')) {
    copyFileSync('resources/assets/dcat/sass/nunito.css', `${distPath}/dcat/css/nunito.css`);
    console.log('  -> dcat/css/nunito.css');
}

// Copy static assets
console.log('Copying static assets...');
if (existsSync('resources/assets/images')) {
    cpSync('resources/assets/images', `${distPath}/images`, { recursive: true });
    console.log('  -> images/');
}
if (existsSync('resources/assets/fonts')) {
    cpSync('resources/assets/fonts', `${distPath}/fonts`, { recursive: true });
    console.log('  -> fonts/');
}
if (existsSync('resources/assets/dcat/plugins')) {
    cpSync('resources/assets/dcat/plugins', `${distPath}/dcat/plugins`, { recursive: true });
    console.log('  -> dcat/plugins/');
}

// Compile extra SCSS files
console.log('Compiling extra SCSS files...');
const extraScssFiles = glob.sync('resources/assets/dcat/extra/*.scss');
for (const file of extraScssFiles) {
    try {
        const outFile = file.replace('resources/assets', distPath).replace('.scss', '.css');
        mkdirSync(dirname(outFile), { recursive: true });
        const result = sass.compile(file, {
            style: isProduction ? 'compressed' : 'expanded',
            sourceMap: true,
            loadPaths: ['node_modules'],
        });
        writeFileSync(outFile, result.css);
        console.log(`  -> ${outFile}`);
    } catch (error) {
        console.error(`Error compiling ${file}:`, error.message);
    }
}

console.log('SCSS compilation complete!');
