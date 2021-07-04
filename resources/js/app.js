require('./bootstrap');

const forms = document.querySelectorAll(".confirm-delete");
if (forms.length > 0) {
    for (const form of forms) {
        form.addEventListener("submit", function (e) {
            e.preventDefault();

            if (confirm("Are you sure you want delete this resource?")) {
                e.target.submit();
            }
        })
    }
}
