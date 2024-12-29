// Only allow one ajax request at a time.
let newsLoading = false;

function news_ajax(count, privileged, authkey) {
    /*
     * count - Number of news items to fetch.
     * privilege - Gotta check your privilege (used to show/hide [Edit] on news).
     * authkey - Either the user's authkey or false. Used for rendering the [Delete] button on the news tool.
     */
    if (newsLoading) {
        return;
    }
    newsLoading = true;
    const offset = document.querySelectorAll('.news_post').length;
    const params = new URLSearchParams({
        action: "news_ajax",
        count: count,
        offset: offset
    });
    fetch(`ajax.php?${params}`)
        .then((response) => response.json())
        .then((data) => {
            if (data.status !== 'success' || !data.response.items) {
                console.error(`ERR ajax_news: ${data.error || 'Unknown data or failure returned.'}`);
                return;
            }

            const moreNewsElement = document.getElementById('more_news');
            const parentElement = moreNewsElement.parentNode;
            const targetClass = moreNewsElement.previousElementSibling.className;

            for (const item of data.response.items) {
                const newDiv = document.createElement('div');
                newDiv.id = `news${item[0]}`;
                newDiv.className = targetClass;

                const headDiv = document.createElement('div');
                headDiv.className = 'head';
                let headHTML = `<strong>${item[1]}</strong> ${item[2]}`;
                // I'm so happy with this condition statement.
                if (privileged && authkey !== false) {
                    // Append [Delete] button and hide [Hide] button if on the news toolbox page
                    headHTML += ' - <a href="tools.php?action=editnews&amp;id=' + item[0] + '" class="brackets">Edit</a> <a class="brackets" href="tools.php?action=deletenews&amp;id=' + item[0] + '&amp;auth=' + authkey + '">Delete</a></div>';
                } else if (privileged) {
                    headHTML += ' - <a href="tools.php?action=editnews&amp;id=' + item[0] + '" class="brackets">Edit</a><span style="float: right;"><a class="brackets" onclick="$(\'#newsbody' + item[0] + '\').gtoggle(); this.innerHTML=(this.innerHTML == \'Hide\' ? \'Show\' : \'Hide\'); return false;" href="#">Hide</a></span></div>';
                } else {
                    headHTML += '<span style="float: right;"><a class="brackets" onclick="$(\'#newsbody' + item[0] + '\').gtoggle(); this.innerHTML=(this.innerHTML == \'Hide\' ? \'Show\' : \'Hide\'); return false;" href="#">Hide</a></span></div>';
                }
                headDiv.innerHTML = headHTML;
                newDiv.appendChild(headDiv);

                // Append the news body.
                const newsBodyDiv = document.createElement('div');
                newsBodyDiv.className = 'pad';
                newsBodyDiv.id = `newsbody${item[0]}`;
                newsBodyDiv.innerHTML = item[3];
                newDiv.appendChild(newsBodyDiv);

                parentElement.insertBefore(newDiv, moreNewsElement);
            }
        })
        .catch((err) => {
            console.error(`WARN ajax_news AJAX get failed: ${err}.`);
        })
        .finally(() => {
            newsLoading = false;
        });
}
