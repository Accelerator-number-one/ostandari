(function () {
  "use strict";

  // ---------- Constants ----------
  const WORD_DELAY = 40; // ms per word chunk
  const CHUNK_SIZE = 2; // words per chunk
  const TOP_PADDING = 48;
  const BOTTOM_PADDING = 128;
  const ADDITIONAL_OFFSET = 16;

  const RESPONSES = [
    "That's an interesting perspective. Let me elaborate on that a bit further. When we consider the implications of what you've shared, several key points come to mind. First, it's important to understand the context and how it relates to broader concepts. This allows us to develop a more comprehensive understanding of the situation. Would you like me to explore any specific aspect of this in more detail?",
    "I appreciate you sharing that. From what I understand, there are multiple layers to consider here. The initial aspect relates to the fundamental principles we're discussing, but there's also a broader context to consider. This reminds me of similar scenarios where the underlying patterns reveal interesting connections. What aspects of this would you like to explore further?",
    "Thank you for bringing this up. It's a fascinating topic that deserves careful consideration. When we analyze the details you've provided, we can identify several important elements that contribute to our understanding. This kind of discussion often leads to valuable insights and new perspectives. Is there a particular element you'd like me to focus on?",
    "Your message raises some compelling points. Let's break this down systematically to better understand the various components involved. There are several key factors to consider, each contributing to the overall picture in unique ways. This kind of analysis often reveals interesting patterns and connections that might not be immediately apparent. What specific aspects would you like to delve into?",
  ];

  // ---------- Vibration helper (safe on unsupported browsers) ----------
  function vibrate(ms) {
    if (navigator && typeof navigator.vibrate === "function") {
      try {
        navigator.vibrate(ms);
      } catch (e) {
        /* no-op */
      }
    }
  }

  // ---------- State ----------
  const state = {
    inputValue: "",
    hasTyped: false,
    activeButton: "none", // "none" | "add" | "deepSearch" | "think"
    isMobile: false,
    viewportHeight: 0,
    messages: [], // { id, content, type, completed, newSection }
    messageSections: [],
    isStreaming: false,
    streamingMessageId: null,
    completedMessages: new Set(),
    activeSectionId: null,
    currentChatId: "chat-" + Date.now(),
    history: [], // { id, title, messages, completed }
    sidebarOpen: false,
  };

  const selectionState = { start: null, end: null };
  let shouldFocusAfterStreaming = false;

  // ---------- DOM refs ----------
  const mainContainer = document.getElementById("main-container");
  const chatContainer = document.getElementById("chat-container");
  const chatInner = document.getElementById("chat-inner");
  const messagesEnd = document.getElementById("messages-end");
  const welcomeScreen = document.getElementById("welcome-screen");
  const inputContainer = document.getElementById("input-container");
  const textarea = document.getElementById("chat-textarea");
  const form = document.getElementById("chat-form");
  const submitBtn = document.getElementById("submit-btn");

  const btnAdd = document.getElementById("btn-add");
  const btnDeepSearch = document.getElementById("btn-deepsearch");
  const btnThink = document.getElementById("btn-think");

  const menuBtn = document.getElementById("menu-btn");
  // const headerNewChatBtn = document.getElementById("header-new-chat-btn");
  const sidebar = document.getElementById("sidebar");
  const sidebarOverlay = document.getElementById("sidebar-overlay");
  const sidebarCloseBtn = document.getElementById("sidebar-close-btn");
  const sidebarNewChatBtn = document.getElementById("sidebar-new-chat-btn");
  const historyList = document.getElementById("history-list");
  const historyEmpty = document.getElementById("history-empty");

  // ---------- Viewport / mobile detection ----------
  function checkMobileAndViewport() {
    const isMobileDevice = window.innerWidth < 768;
    state.isMobile = isMobileDevice;
    const vh = window.innerHeight;
    state.viewportHeight = vh;

    if (isMobileDevice) {
      mainContainer.style.height = vh + "px";
    } else {
      mainContainer.style.height = "100svh";
    }
  }

  window.addEventListener("resize", checkMobileAndViewport);
  checkMobileAndViewport();

  function getContentHeight() {
    return state.viewportHeight - TOP_PADDING - BOTTOM_PADDING - ADDITIONAL_OFFSET;
  }

  // ---------- Selection state save/restore (for toggle buttons) ----------
  function saveSelectionState() {
    selectionState.start = textarea.selectionStart;
    selectionState.end = textarea.selectionEnd;
  }

  function restoreSelectionState() {
    if (selectionState.start !== null && selectionState.end !== null) {
      textarea.focus();
      textarea.setSelectionRange(selectionState.start, selectionState.end);
    } else {
      textarea.focus();
    }
  }

  function focusTextarea() {
    if (!state.isMobile) {
      textarea.focus();
    }
  }

  // ---------- Rendering ----------
  function organizeSections() {
    if (state.messages.length === 0) {
      state.messageSections = [];
      state.activeSectionId = null;
      return;
    }

    const sections = [];
    let currentSection = {
      id: "section-" + Date.now() + "-0",
      messages: [],
      isNewSection: false,
      sectionIndex: 0,
    };

    state.messages.forEach((message) => {
      if (message.newSection) {
        if (currentSection.messages.length > 0) {
          sections.push(Object.assign({}, currentSection, { isActive: false }));
        }
        const newSectionId = "section-" + Date.now() + "-" + sections.length;
        currentSection = {
          id: newSectionId,
          messages: [message],
          isNewSection: true,
          isActive: true,
          sectionIndex: sections.length,
        };
        state.activeSectionId = newSectionId;
      } else {
        currentSection.messages.push(message);
      }
    });

    if (currentSection.messages.length > 0) {
      sections.push(currentSection);
    }

    state.messageSections = sections;
  }

  function shouldApplyHeight(sectionIndex) {
    return sectionIndex > 0;
  }

  function createIcon(pathHTML, extraClass) {
    const wrapper = document.createElement("span");
    wrapper.className = "icon-inline";
    wrapper.innerHTML =
      '<svg viewBox="0 0 24 24" class="icon ' + (extraClass || "") + '">' + pathHTML + "</svg>";
    return wrapper.firstElementChild;
  }

  const ICONS = {
    refresh: '<path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>',
    copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    share: '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 3.9M15.4 6.6l-6.8 3.9"/>',
    thumbsUp: '<path d="M7 10v12"/><path d="M15 5.88 14 10h5.83a2 2 0 0 1 1.92 2.56l-2.33 8A2 2 0 0 1 17.5 22H4a2 2 0 0 1-2-2v-8a2 2 0 0 1 2-2h2.76a2 2 0 0 0 1.79-1.11L12 2a3.13 3.13 0 0 1 3 3.88Z"/>',
    thumbsDown: '<path d="M17 14V2"/><path d="M9 18.12 10 14H4.17a2 2 0 0 1-1.92-2.56l2.33-8A2 2 0 0 1 6.5 2H20a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2h-2.76a2 2 0 0 0-1.79 1.11L12 22a3.13 3.13 0 0 1-3-3.88Z"/>',
  };

  function renderMessage(message) {
    const isCompleted = state.completedMessages.has(message.id);

    const row = document.createElement("div");
    row.className = "message-row " + (message.type === "user" ? "user" : "system");

    const bubble = document.createElement("div");
    bubble.className = "message-bubble";

    if (message.content) {
      const span = document.createElement("span");
      if (message.type === "system" && !isCompleted) {
        span.className = "fade-in-word";
      }
      span.textContent = message.content;
      bubble.appendChild(span);
    }

    if (message.id === state.streamingMessageId) {
      const streamSpan = document.createElement("span");
      streamSpan.className = "streaming-span";
      streamSpan.id = "streaming-span-" + message.id;
      bubble.appendChild(streamSpan);
    }

    row.appendChild(bubble);

    if (message.type === "system" && message.completed) {
      const actions = document.createElement("div");
      actions.className = "message-actions";

      [
        ["refresh", ICONS.refresh],
        ["copy", ICONS.copy],
        ["share", ICONS.share],
        ["thumbsUp", ICONS.thumbsUp],
        ["thumbsDown", ICONS.thumbsDown],
      ].forEach(([name, path]) => {
        const btn = document.createElement("button");
        btn.type = "button";
        btn.appendChild(createIcon(path, "small"));
        actions.appendChild(btn);
      });

      row.appendChild(actions);
    }

    return row;
  }

  function render() {

if (state.messages.length === 0) {

    requestAnimationFrame(() => {
        welcomeScreen.classList.remove("hide");
    });

} else {

    welcomeScreen.classList.add("hide");

}

    organizeSections();


    const oldEnd = document.getElementById("messages-end");
if (oldEnd) {
    oldEnd.remove();
}

chatInner.innerHTML = "";
    // Clear chat inner (keep messagesEnd marker, re-append at the end)
    chatInner.innerHTML = "";

    state.messageSections.forEach((section, sectionIndex) => {
      const sectionEl = document.createElement("div");

      if (section.isNewSection) {
        const inner = document.createElement("div");
        inner.className = "message-section new-section";
        if (section.isActive && shouldApplyHeight(section.sectionIndex)) {
          inner.style.height = getContentHeight() + "px";
        }
        section.messages.forEach((m) => inner.appendChild(renderMessage(m)));
        sectionEl.appendChild(inner);
      } else {
        const inner = document.createElement("div");
        inner.className = "message-section";
        section.messages.forEach((m) => inner.appendChild(renderMessage(m)));
        sectionEl.appendChild(inner);
      }

      chatInner.appendChild(sectionEl);
    });

    const end = document.createElement("div");
end.id = "messages-end";
chatInner.appendChild(end);

    // Scroll behavior: for sections after the first, scroll to max
    if (state.messageSections.length > 1) {
      setTimeout(() => {
        chatContainer.scrollTo({ top: chatContainer.scrollHeight, behavior: "smooth" });
      }, 100);
    }
  }


  // ---------- Streaming simulation ----------
  function simulateTextStreaming(text, messageId) {
    const words = text.split(" ");
    let currentIndex = 0;
    state.isStreaming = true;
    updateInputUIState();

    return new Promise((resolve) => {
      const streamSpan = document.getElementById("streaming-span-" + messageId);

      const interval = setInterval(() => {
        if (currentIndex < words.length) {
          const nextIndex = Math.min(currentIndex + CHUNK_SIZE, words.length);
          const chunk = words.slice(currentIndex, nextIndex).join(" ") + " ";
          currentIndex = nextIndex;

          if (streamSpan) {
            const wordSpan = document.createElement("span");
            wordSpan.className = "fade-in-word";
            wordSpan.textContent = chunk;
            streamSpan.appendChild(wordSpan);
          }

          // Keep view pinned to bottom while streaming in the first section
          chatContainer.scrollTop = chatContainer.scrollHeight;
        } else {
          clearInterval(interval);
          resolve();
        }
      }, WORD_DELAY);
    });
  }

  function getAIResponse() {
    return RESPONSES[Math.floor(Math.random() * RESPONSES.length)];
  }

  async function simulateAIResponse(userMessage) {
    const response = getAIResponse();
    const messageId = Date.now().toString() + "-ai";
    state.streamingMessageId = messageId;

    state.messages.push({
      id: messageId,
      content: "",
      type: "system",
    });
    render();

    setTimeout(() => {
      vibrate(50);
    }, 200);

    await simulateTextStreaming(response, messageId);

    const msg = state.messages.find((m) => m.id === messageId);
    if (msg) {
      msg.content = response;
      msg.completed = true;
    }
    state.completedMessages.add(messageId);

    vibrate(50);

    state.streamingMessageId = null;
    state.isStreaming = false;
    render();
    updateInputUIState();

    if (shouldFocusAfterStreaming && !state.isMobile) {
      focusTextarea();
      shouldFocusAfterStreaming = false;
    }
  }

  // ---------- Sidebar ----------
  function openSidebar() {
    state.sidebarOpen = true;
    sidebar.classList.add("open");
    sidebarOverlay.classList.add("open");
  }

  function closeSidebar() {
    state.sidebarOpen = false;
    sidebar.classList.remove("open");
    sidebarOverlay.classList.remove("open");
  }

  function toggleSidebar() {
    if (state.sidebarOpen) {
      closeSidebar();
    } else {
      openSidebar();
    }
  }

  menuBtn.addEventListener("click", toggleSidebar);
  sidebarCloseBtn.addEventListener("click", closeSidebar);
  sidebarOverlay.addEventListener("click", closeSidebar);

  // ---------- Chat history ----------
  function makeTitleFromMessages(messages) {
    const firstUser = messages.find((m) => m.type === "user");
    if (!firstUser || !firstUser.content) return "New chat";
    const text = firstUser.content.trim();
    return text.length > 32 ? text.slice(0, 32) + "…" : text;
  }

  function cloneMessages(messages) {
    return messages.map((m) => Object.assign({}, m));
  }

  // Save the currently active chat into history (in place if it already exists there)
  function saveCurrentChatToHistory() {
    if (state.messages.length === 0) return;

    const entry = {
      id: state.currentChatId,
      title: makeTitleFromMessages(state.messages),
      messages: cloneMessages(state.messages),
      completed: Array.from(state.completedMessages),
    };

    state.history = state.history.filter((h) => h.id !== entry.id);
    state.history.unshift(entry);
  }

  function resetActiveChat(newId) {
    state.messages = [];
    state.messageSections = [];
    state.completedMessages = new Set();
    state.streamingMessageId = null;
    state.isStreaming = false;
    state.currentChatId = newId || "chat-" + Date.now();
  }

  function startNewChat() {
    if (state.isStreaming) return;

    saveCurrentChatToHistory();
    resetActiveChat();

    state.inputValue = "";
    state.hasTyped = false;
    textarea.value = "";
    textarea.style.height = "auto";

    render();
    renderHistory();
    updateInputUIState();
    closeSidebar();
    focusTextarea();
  }

  function loadHistoryChat(id) {
    if (state.isStreaming) return;

    const entry = state.history.find((h) => h.id === id);
    if (!entry) return;

    // Preserve whatever is currently on screen before switching
    saveCurrentChatToHistory();

    state.history = state.history.filter((h) => h.id !== id);

    state.messages = cloneMessages(entry.messages);
    state.completedMessages = new Set(entry.completed);
    state.streamingMessageId = null;
    state.isStreaming = false;
    state.currentChatId = entry.id;

    render();
    renderHistory();
    updateInputUIState();
    closeSidebar();
  }

  function renderHistory() {
    historyList.innerHTML = "";

    if (state.history.length === 0) {
      const empty = document.createElement("div");
      empty.className = "history-empty";
      empty.id = "history-empty";
      empty.textContent = "No chat history yet";
      historyList.appendChild(empty);
      return;
    }

    state.history.forEach((entry) => {
      const item = document.createElement("button");
      item.type = "button";
      item.className = "history-item";
      item.textContent = entry.title;
      item.addEventListener("click", () => loadHistoryChat(entry.id));
      historyList.appendChild(item);
    });
  }

  // headerNewChatBtn.addEventListener("click", startNewChat);
  sidebarNewChatBtn.addEventListener("click", startNewChat);

  // ---------- Input handling ----------
  function updateInputUIState() {
    submitBtn.disabled = !state.inputValue.trim() || state.isStreaming;
    submitBtn.classList.toggle("active", state.hasTyped);
    inputContainer.classList.toggle("streaming", state.isStreaming);
    textarea.placeholder = state.isStreaming ? "Waiting for response..." : "Ask Anything";

    [btnAdd, btnDeepSearch, btnThink].forEach((b) => (b.disabled = state.isStreaming));
    sidebarNewChatBtn.disabled = state.isStreaming;
    // headerNewChatBtn.disabled = state.isStreaming;
  }

  textarea.addEventListener("input", () => {
    if (state.isStreaming) return;

    state.inputValue = textarea.value;

    if (state.inputValue.trim() !== "" && !state.hasTyped) {
      state.hasTyped = true;
    } else if (state.inputValue.trim() === "" && state.hasTyped) {
      state.hasTyped = false;
    }

    textarea.style.height = "auto";
    const newHeight = Math.max(24, Math.min(textarea.scrollHeight, 160));
    textarea.style.height = newHeight + "px";

    updateInputUIState();
  });

  textarea.addEventListener("focus", () => {
    textarea.scrollIntoView({ behavior: "smooth", block: "center" });
  });

  textarea.addEventListener("keydown", (e) => {
    if (!state.isStreaming && e.key === "Enter" && e.metaKey) {
      e.preventDefault();
      handleSubmit(e);
      return;
    }
    if (!state.isStreaming && !state.isMobile && e.key === "Enter" && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  });

  inputContainer.addEventListener("click", (e) => {
    const target = e.target;
    if (target === inputContainer || (!target.closest("button") && target !== textarea)) {
      textarea.focus();
    }
  });

  function toggleButton(button) {
    if (state.isStreaming) return;

    saveSelectionState();
    state.activeButton = state.activeButton === button ? "none" : button;

    [btnAdd, btnDeepSearch, btnThink].forEach((b) => b.classList.remove("active"));
    if (button === "add" && state.activeButton === "add") btnAdd.classList.add("active");
    if (button === "deepSearch" && state.activeButton === "deepSearch") btnDeepSearch.classList.add("active");
    if (button === "think" && state.activeButton === "think") btnThink.classList.add("active");

    setTimeout(restoreSelectionState, 0);
  }

  btnAdd.addEventListener("click", () => toggleButton("add"));
  btnDeepSearch.addEventListener("click", () => toggleButton("deepSearch"));
  btnThink.addEventListener("click", () => toggleButton("think"));

  function handleSubmit(e) {
    e.preventDefault();
    if (!state.inputValue.trim() || state.isStreaming) return;

    vibrate(50);

    const userMessage = state.inputValue.trim();
    const shouldAddNewSection = state.messages.length > 0;

    state.messages.push({
      id: "user-" + Date.now(),
      content: userMessage,
      type: "user",
      newSection: shouldAddNewSection,
    });

    state.inputValue = "";
    state.hasTyped = false;
    state.activeButton = "none";
    [btnAdd, btnDeepSearch, btnThink].forEach((b) => b.classList.remove("active"));

    textarea.value = "";
    textarea.style.height = "auto";

    render();
    updateInputUIState();

    if (!state.isMobile) {
      shouldFocusAfterStreaming = true;
      focusTextarea();
    } else {
      textarea.blur();
    }

    simulateAIResponse(userMessage);
  }

  form.addEventListener("submit", handleSubmit);

  // ---------- Initial focus ----------
  if (!state.isMobile) {
    textarea.focus();
  }

  updateInputUIState();
  render();
})();