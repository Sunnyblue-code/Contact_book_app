document
  .getElementById("loginForm")
  .addEventListener("submit", async function (e) {
    e.preventDefault();

    const formData = {
      username: document.getElementById("username").value,
      password: document.getElementById("password").value,
      remember: document.getElementById("remember").checked,
    };

    try {
      const response = await fetch("/auth/login", {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
        },
        body: JSON.stringify(formData),
      });

      const data = await response.json();
      if (data.success) {
        alert("Login successful!");
        window.location.href = "/dashboard"; // Redirect to dashboard or home page
      } else {
        alert("Error: " + data.error);
      }
    } catch (error) {
      console.error("Error:", error);
      alert("An error occurred during login");
    }
  });
