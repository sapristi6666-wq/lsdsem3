const esbuild = require('esbuild');

const isWatch = process.argv.includes('--watch');

const config = {
    entryPoints: ['js/dashboard.js'],
    bundle: true,
    minify: !isWatch,
    sourcemap: isWatch,
    outfile: 'js/dashboard.min.js',
    target: ['es2018'],
    format: 'iife',
    loader: { '.js': 'js' },
};

if (isWatch) {
    esbuild.context(config).then(ctx => {
        console.log('👀 Watch mode activé — attente de modifications...');
        ctx.watch();
    });
} else {
    esbuild.build(config).then(() => {
        console.log('✅ Build terminé : js/dashboard.min.js');
    }).catch(() => process.exit(1));
}