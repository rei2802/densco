```javascript
function askAI(text) {
  return fetch("/api/chatbot.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      message: text
    })
  })
  .then(function (response) {
    return response.json().then(function (data) {
      if (!response.ok) {
        throw new Error(
          data.error || "Request failed with status " + response.status
        );
      }

      return data;
    });
  })
  .then(function (data) {
    if (data && data.success && data.message) {
      return cleanAI(data.message);
    }

    throw new Error(
      (data && data.error) || "No AI response received."
    );
  });
}
```
