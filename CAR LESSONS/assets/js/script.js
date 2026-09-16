const toggle = document.querySelector("#togglePassword");
const password = document.querySelector("#password");

if (toggle && password) {
    toggle.addEventListener("click", () => {
        const type = password.getAttribute("type") === "password" ? "text" : "password";
        password.setAttribute("type", type);
        toggle.classList.toggle("fa-eye", type === "password");
        toggle.classList.toggle("fa-eye-slash", type === "text");
        toggle.setAttribute("aria-label", type === "password" ? "Show password" : "Hide password");
    });
}
