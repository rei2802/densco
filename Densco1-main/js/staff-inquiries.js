(function () {
  "use strict";

  const D = window.DHP;
  if (!D.staffChat) return;

  const list = document.getElementById("list");
  const log = document.getElementById("thread");
  const title = document.getElementById("title");
  const state = document.getElementById("chatState");
  const errorNotice = document.getElementById("chatError");
  const form = document.getElementById("replyForm");
  const input = document.getElementById("replyIn");
  const end = document.getElementById("endConversation");
  const labels = { waiting: "Waiting for reply", answered: "Answered", closed: "Ended" };
  let selected = /^#[1-9]\d*$/.test(location.hash) ? Number(location.hash.slice(1)) : null;
  let queue = [];
  let thread = null;
  let cursor = 0;
  let version = 0;
  let refreshing = false;
  let saving = false;
  let pendingReply = null;
  let writeError = false;

  function showError(message) {
    errorNotice.textContent = message;
    errorNotice.classList.toggle("hide", !message);
  }

  function renderList() {
    const selectedButton = list.querySelector(":focus");
    const markup = queue.length
      ? queue.map((item) =>
          `<button type="button" class="inq-item ${item.id === selected ? "on" : ""}" data-id="${Number(item.id)}"
             aria-pressed="${item.id === selected}"><span class="inq-item-header"><b>${D.esc(item.customer)}</b>
             <span class="badge ${item.status === "waiting" ? "pending" : "completed"}">${labels[item.status]}</span></span>
             <span class="inq-preview">${D.esc(item.preview || "")}</span></button>`
        ).join("")
      : '<p class="empty">No staff conversations yet.</p>';
    list.innerHTML = markup;
    if (selectedButton) list.querySelector(`[data-id="${Number(selectedButton.dataset.id)}"]`)?.focus();
  }

  function renderThread(result) {
    thread = result;
    if (thread) {
      thread.messages.forEach((message) => {
        if (message.id <= cursor) return;
        const bubble = document.createElement("div");
        bubble.className = "bubble " + (message.sender === "staff" ? "staff" : "customer");
        bubble.textContent = message.text;
        log.append(bubble);
        cursor = message.id;
      });
      if (thread.messages.length) log.scrollTop = log.scrollHeight;
    }
    const current = queue.find((item) => item.id === selected);
    title.textContent = current ? current.customer + " · #" + selected : "Select a conversation";
    if (thread) state.textContent = labels[thread.status];
    const canReply = thread && thread.status !== "closed";
    form.classList.toggle("hide", !canReply);
    end.classList.toggle("hide", !canReply);
  }

  function select(id) {
    selected = id;
    history.replaceState(null, "", id ? "#" + id : location.pathname + location.search);
    version++;
    cursor = 0;
    pendingReply = null;
    writeError = false;
    input.value = "";
    log.replaceChildren();
    showError("");
    state.textContent = id ? "Loading conversation…" : "Choose a conversation to view and reply.";
    renderList();
    renderThread(null);
    refresh();
  }

  function report(error, writing) {
    if (error.status === 401 || error.status === 403) {
      queue = [];
      selected = null;
      cursor = 0;
      pendingReply = null;
      version++;
      input.value = "";
      log.replaceChildren();
      list.textContent = "Sign in with an authorized staff account to view conversations.";
      renderThread(null);
    }
    writeError = writing;
    showError(error.status ? error.message :
      writing ? "Could not confirm the change. Try again." : "Staff chat could not connect. Reconnecting…");
  }

  async function refresh() {
    if (refreshing || saving || document.hidden) return;
    refreshing = true;
    const requestVersion = version;
    try {
      const [queueResult, threadResult] = await Promise.all([
        D.staffChat({ action: "queue" }),
        selected ? D.staffChat({ action: "thread", id: selected, after: cursor }) : null
      ]);
      if (requestVersion !== version) return;
      if (!writeError) showError("");
      if (JSON.stringify(queue) !== JSON.stringify(queueResult.conversations) || !list.querySelector(".inq-item, .empty")) {
        queue = queueResult.conversations;
        renderList();
      }
      if (selected && !threadResult.thread) {
        select(null);
        return;
      }
      renderThread(threadResult?.thread ?? null);
    } catch (error) {
      if (requestVersion === version) report(error, false);
    } finally {
      refreshing = false;
      if (requestVersion !== version && selected) refresh();
    }
  }

  async function write(action) {
    if (saving || !thread || thread.status === "closed") return;
    const text = input.value.trim();
    if (action === "send" && !text) return;
    if (action === "send" && pendingReply?.text !== text) {
      pendingReply = { text, token: D.staffChatToken() };
    }
    saving = true;
    writeError = false;
    showError("");
    version++;
    const requestVersion = version;
    form.querySelector("button").disabled = true;
    end.disabled = true;
    try {
      const result = await D.staffChat({ after: cursor }, { action, id: selected, ...pendingReply });
      if (requestVersion === version) {
        renderThread(result.thread);
        if (action === "send" && input.value.trim() === text) input.value = "";
        pendingReply = null;
      }
      D.refreshStaffChatBadge();
    } catch (error) {
      if (requestVersion === version) report(error, true);
    } finally {
      saving = false;
      form.querySelector("button").disabled = false;
      end.disabled = false;
      refresh();
    }
  }

  list.addEventListener("click", (event) => {
    const button = event.target.closest("button[data-id]");
    if (button && Number(button.dataset.id) !== selected) select(Number(button.dataset.id));
  });
  form.addEventListener("submit", (event) => {
    event.preventDefault();
    write("send");
  });
  end.addEventListener("click", () => write("close"));
  document.addEventListener("visibilitychange", refresh);
  setInterval(refresh, 2000);
  if (selected) select(selected);
  else {
    state.textContent = "Choose a conversation to view and reply.";
    refresh();
  }
})();
