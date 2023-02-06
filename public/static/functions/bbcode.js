var BBCode = {
    spoiler: link => {
        if ($(link.nextSibling).has_class('hidden')) {
            $(link.nextSibling).gshow();
            $(link).html('Hide');
        } else {
            $(link.nextSibling).ghide();
            $(link).html('Show');
        }
    },
    render_tex: elem => {
        $(elem).find('katex:not([rendered])').each((i, e) => {
            $(e).attr('rendered', true);
            katex.render(e.innerText, e, {
                throwOnError: false, maxSize: 50
            })
        })
    },
    run_renderer: elem => {
        BBCode.render_tex(elem);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    BBCode.run_renderer(document.body);
})
