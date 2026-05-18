/**
 * esbuild script for Filament AlpineComponent bundles.
 *
 * Compiles each entry point into a self-contained ES module with all
 * dependencies inlined. Output is consumed by Filament's
 * `php artisan filament:assets`, which copies it into /public so the
 * `x-load` / `x-load-src` lazy loader can fetch it on demand.
 *
 *   node bin/build.js          # one-shot production build
 *   node bin/build.js --dev    # watch + inline sourcemaps
 *
 * Add new components: drop a `compile({...})` call at the bottom.
 */
import * as esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

async function compile(options) {
    const context = await esbuild.context(options)

    if (isDev) {
        await context.watch()
    } else {
        await context.rebuild()
        await context.dispose()
    }
}

const defaultOptions = {
    define: {
        'process.env.NODE_ENV': isDev ? `'development'` : `'production'`,
    },
    bundle: true,
    mainFields: ['module', 'main'],
    platform: 'neutral',
    sourcemap: isDev ? 'inline' : false,
    sourcesContent: isDev,
    treeShaking: true,
    target: ['es2020'],
    minify: !isDev,
    plugins: [
        {
            name: 'watchPlugin',
            setup(build) {
                build.onStart(() => {
                    console.log(
                        `Build started at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                    )
                })

                build.onEnd((result) => {
                    if (result.errors.length > 0) {
                        console.log(
                            `Build failed at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                            result.errors,
                        )
                    } else {
                        console.log(
                            `Build finished at ${new Date(Date.now()).toLocaleTimeString()}: ${build.initialOptions.outfile}`,
                        )
                    }
                })
            },
        },
    ],
}

compile({
    ...defaultOptions,
    entryPoints: ['./resources/js/components/pirep-performance-chart.js'],
    outfile: './resources/js/dist/components/pirep-performance-chart.js',
})

compile({
    ...defaultOptions,
    entryPoints: ['./resources/js/components/pirep-landing-analysis.js'],
    outfile: './resources/js/dist/components/pirep-landing-analysis.js',
})
