"use strict";

document.addEventListener('DOMContentLoaded', () => {
    let total = 1;
    let block = document.getElementById('answer_block');

    document.getElementById('poll-add').addEventListener('click', (e) => {
        e.preventDefault();
        if (total >= 25) {
            return;
        }
        block.appendChild(document.createElement("br"));
        let input         = document.createElement("input");
        input.type        = "text";
        input.id          = "answer_" + total++;
        input.className   = "required";
        input.name        = "answers[]";
        input.style.width = "90%";
        block.appendChild(input);
    });

    document.getElementById('poll-remove').addEventListener('click', (e) => {
        e.preventDefault();
        if (total-- == 1) {
            return;
        }
        block.removeChild(block.lastChild);
        block.removeChild(block.lastChild);
    });
});
