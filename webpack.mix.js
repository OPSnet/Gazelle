let mix = require('laravel-mix');

let sassopts = {
    sassOptions: {
        outputStyle: mix.inProduction() ? 'compressed' : 'expanded',
    }
};

mix.disableSuccessNotifications()
    .setPublicPath('.')
    .options({
        processCssUrls: false,
    })
    .sass('sass/80char/style.scss',             'public/static/styles/80char',             sassopts)
    .sass('sass/apollostage/style.scss',        'public/static/styles/apollostage',        sassopts)
    .sass('sass/apollostage_coffee/style.scss', 'public/static/styles/apollostage_coffee', sassopts)
    .sass('sass/apollostage_sunset/style.scss', 'public/static/styles/apollostage_sunset', sassopts)
    .sass('sass/dark_ambient/style.scss',       'public/static/styles/dark_ambient',       sassopts)
    .sass('sass/dark_cake/style.scss',          'public/static/styles/dark_cake',          sassopts)
    .sass('sass/datetime_picker/style.scss',    'public/static/styles/datetime_picker',    sassopts)
    .sass('sass/kuro/style.scss',               'public/static/styles/kuro',               sassopts)
    .sass('sass/layer_cake/style.scss',         'public/static/styles/layer_cake',         sassopts)
    .sass('sass/linohaze/style.scss',           'public/static/styles/linohaze',           sassopts)
    .sass('sass/post_office/style.scss',        'public/static/styles/post_office',        sassopts)
    .sass('sass/postmod/style.scss',            'public/static/styles/postmod',            sassopts)
    .sass('sass/proton/style.scss',             'public/static/styles/proton',             sassopts)
    .sass('sass/public/style.scss',             'public/static/styles/public',             sassopts)
    .sass('sass/tiles/style.scss',              'public/static/styles/tiles',              sassopts)
    .sass('sass/tooltipster/style.scss',        'public/static/styles/tooltipster',        sassopts)
    .sass('sass/xanax_cake/style.scss',         'public/static/styles/xanax_cake',         sassopts)
    .sass('sass/global.scss',                   'public/static/styles',                    sassopts)
    .sass('sass/log.scss',                      'public/static/styles',                    sassopts)
    .sass('sass/minimal_mod_alt.scss',          'public/static/styles',                    sassopts)
    .sass('sass/musicbrainz.scss',              'public/static/styles',                    sassopts)
;

if (mix.inProduction()) {
    mix.version();
} else {
    mix.sourceMaps(false, 'source-map');
    mix.browserSync({
        proxy: 'localhost:7001',
        notify: false,
        files: [
            'public/static/styles/**/*.css',
            'public/static/functions/**/*.js',
            'templates/**/*.twig',
        ],
    });
}
