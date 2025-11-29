async function openGift(e) {
    e.target.removeEventListener('click', openGift);
    const form = new FormData();
    form.append('auth', document.body.dataset.auth);
    form.append('d', e.target.dataset.d);
    form.append('i', e.target.dataset.i);
    form.append('s', e.target.dataset.s);
    form.append('u', document.body.dataset.id);
    const response = await fetch(
        '?action=open', {
            'method': 'POST',
            'body': form,
        }
    );
    const data = await response.json();
    if (data.status === 'success') {
        e.target.src = e.target.src.replace('ready', 'open');
        setTimeout(() => {
            document.getElementById('result').innerHTML
                = '<h3>You have won the following: <b>'
                    + data.response.description
                    + '</b></h3>';
        }, 500);
    } else {
        document.getElementById('result').innerHTML
            = "<h3>Computer says \"" + data.error.reason + ".\"</h3>";
    }
}

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('gift-box')?.addEventListener('click', openGift);
});
