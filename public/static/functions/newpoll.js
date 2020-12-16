var AnswerCount = 1;

function AddAnswerField() {
    if (AnswerCount >= 25) {
        return;
    }
    var AnswerField = document.createElement("input");
    AnswerField.type = "text";
    AnswerField.id = "answer_"+AnswerCount;
    AnswerField.className = "required";
    AnswerField.name = "answers[]";
    AnswerField.style.width = "90%";

    var x = $('#answer_block').raw();
    x.appendChild(document.createElement("br"));
    x.appendChild(AnswerField);
    AnswerCount++;
}

function RemoveAnswerField() {
    if (AnswerCount == 1) {
        return;
    }
    var x = $('#answer_block').raw();
    for (i = 0; i < 2; i++) {
        x.removeChild(x.lastChild);
    }
    AnswerCount--;
}
