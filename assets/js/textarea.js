document.querySelector('textarea').addEventListener('keydown', function (e) {
    if (!e) {
        return;
    }

    if (e.keyCode == 9) {
        var start = this.selectionStart;
        var end = this.selectionEnd;
        var target = e.target;
        var value = target.value;

        target.value = value.substring(0, start) + '\t' + value.substring(end);
        this.selectionStart = start + 1;
        this.selectionEnd = start + 1;

        e.preventDefault ();
    }
});
