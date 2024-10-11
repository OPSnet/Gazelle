/* global tooltip_delay */

"use strict";

function checkCollageCats(value) {
    let e = document.getElementsByClassName('collagecat');
    for (let i = 0; i < e.length; i++) {
        e[i].checked = value;
    }
}

function invertCollageCats() {
    let e = document.getElementsByClassName('collagecat');
    for (let i = 0; i < e.length; i++) {
        e[i].checked = !e[i].checked;
    }
}

async function CollageSubscribe(id) {
    await fetch(
        "userhistory.php?action=collage_subscribe&collageid=" + id
        + "&auth=" + document.body.dataset.auth
    );
    let subscribeLink = document.getElementById("subscribelink" + id);
    if (subscribeLink) {
        subscribeLink.firstChild.nodeValue = 
            (subscribeLink.firstChild.nodeValue.charAt(0) == 'U')
            ? "Subscribe"
            : "Unsubscribe";
    }
}

let collageShow = {
    pg:0,
    pages:false,
    wrap:false,
    init:function(collagePages) {
        this.wrap = document.getElementById('coverart');
        this.pages = collagePages;
        this.max = this.pages.length - 1;
    },
    selected:function() {
        return $('.linkbox .selected').raw();
    },
    createUL:function(data) {
        let ul = document.createElement('ul');
        $(ul).add_class('collage_images');
        ul.id = 'collage_page' + this.pg;
        $(ul).html(data);
        if ($.fn.tooltipster) {
            $('.tooltip_interactive', ul).tooltipster({
                interactive: true,
                interactiveTolerance: 500,
                delay: tooltip_delay,
                updateAnimation: false,
                maxWidth: 400
            });
        } else {
            $('.tooltip_interactive', ul).each(function() {
                if ($(this).data('title-plain')) {
                    $(this).attr('title', $(this).data('title-plain')).removeData('title-plain');
                }
            });
        }
        this.wrap.appendChild(ul);
        return ul;
    },
    page:function(num, el) {
        this.pg = num;

        let ul = $('#collage_page' + num).raw();
        if (!ul) {
            let covers = this.pages[num];
            if (covers) {
                ul = this.createUL(covers);
            }
        }

        $('.collage_images').ghide();
        $(ul).gshow();

        let s = this.selected();
        if (s) {
            $(s).remove_class('selected');
        }

        if (el) {
            $(el.parentNode).add_class('selected');
        }
        else {
            $('#pagelink' + this.pg).add_class('selected');
        }

        // Toggle the page number links
        let first = (this.max - this.pg < 2)
            ? Math.max(0, this.pg - 2)
            : Math.max(this.max - 4, 0);
        let last = Math.min(first + 4, this.max);
        for (let i = 0; i < first; i++) {
            $('#pagelink' + i).ghide();
        }
        for (let i = first; i <= last; i++) {
            $('#pagelink' + i).gshow();
        }
        for (let i = last + 1; i <= this.max; i++) {
            $('#pagelink' + i).ghide();
        }

        // Toggle the first, prev, next, and last links
        if (this.pg > 0) {
            $('#prevpage').remove_class('invisible');
        } else {
            $('#prevpage').add_class('invisible');
        }
        if (this.pg > 1) {
            $('#firstpage').remove_class('invisible');
        } else {
            $('#firstpage').add_class('invisible');
        }
        if (this.pg < this.max) {
            $('#nextpage').remove_class('invisible');
        } else {
            $('#nextpage').add_class('invisible');
        }
        if (this.pg < this.max - 1) {
            $('#lastpage').remove_class('invisible');
        } else {
            $('#lastpage').add_class('invisible');
        }

        // Toggle the bar
        if ((last == this.max) && (this.pg != this.max)) {
            $('#nextbar').gshow();
        } else {
            $('#nextbar').ghide();
        }
    },
    nextPage:function() {
        this.pg = this.pg < this.max ? this.pg + 1 : this.pg;
        this.pager();
    },
    prevPage:function() {
        this.pg = this.pg > 0 ? this.pg - 1 : this.pg;
        this.pager();
    },
    pager:function() {
        this.page(this.pg, $('#pagelink' + this.pg).raw().firstChild);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    let all = document.getElementById('cat-all');
    if (all) {
        all.addEventListener('click', () => {
            checkCollageCats(true);
        });
    }

    let none = document.getElementById('cat-none');
    if (none) {
        none.addEventListener('click', () => {
            checkCollageCats(false);
        });
    }

    let inv = document.getElementById('cat-invert');
    if (inv) {
        inv.addEventListener('click', () => {
            invertCollageCats();
        });
    }
});
