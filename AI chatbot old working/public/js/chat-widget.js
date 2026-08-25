/**
 * WP AI Chatbot - Frontend Chat Logic
 */

document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    const chatWindow = document.getElementById('wp-ai-chatbot-window');
    const closeBtn   = document.getElementById('wp-ai-close-chat');
    const chatInput  = document.getElementById('wp-ai-chat-input');
    const sendBtn    = document.getElementById('wp-ai-send-btn');
    const chatBody   = document.getElementById('wp-ai-chat-body');
    const chatToggle = document.getElementById('wp-ai-chatbot-toggle');
    const fabContainer = document.getElementById('wp-ai-fab-container');
    const aiChatBtn = document.getElementById('wp-ai-open-chat-btn');

    const STORAGE_KEY = 'wp_ai_chat_history_permanent';
    const PRE_CHAT_KEY = 'wp_ai_pre_chat_submitted';

    if (!chatWindow) return;

    // 🚀 CHAT WINDOW TOGGLE LOGIC
    function toggleChat() {
        chatWindow.classList.toggle('wp-ai-hidden');
        document.body.classList.toggle('wp-ai-chat-open');
        if (!chatWindow.classList.contains('wp-ai-hidden')) { 
            chatInput.focus(); 
            scrollToBottom(); 
        }
    }

    // 🚀 SMART FAB (HOVER & CLICK SYNC LOGIC)
    let isForcedClosed = false;

    if (fabContainer) {
        fabContainer.addEventListener('mouseenter', () => {
            if (window.innerWidth > 480 && fabContainer.classList.contains('has-wa-menu') && !isForcedClosed) {
                fabContainer.classList.add('active');
            }
        });

        fabContainer.addEventListener('mouseleave', () => {
            if (window.innerWidth > 480 && fabContainer.classList.contains('has-wa-menu')) {
                fabContainer.classList.remove('active');
                isForcedClosed = false;
            }
        });
    }

    if (chatToggle && fabContainer) {
        chatToggle.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();

            const isChatOpen = !chatWindow.classList.contains('wp-ai-hidden');

            if (isChatOpen) {
                toggleChat(); 
                fabContainer.classList.remove('active');
                return;
            }
            
            if (fabContainer.classList.contains('has-wa-menu')) {
                if (fabContainer.classList.contains('active')) {
                    fabContainer.classList.remove('active');
                    isForcedClosed = true; 
                } else {
                    fabContainer.classList.add('active');
                    isForcedClosed = false;
                }
            } else {
                toggleChat();
            }
        });
    }

    // 🚀 AI BUBBLE CLICK
    if (aiChatBtn) {
        aiChatBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation(); 
            toggleChat(); 
            if (fabContainer) fabContainer.classList.remove('active'); 
        });
    }

    // 🚀 CLOSE CHAT BUTTON (X)
    if (closeBtn) {
        closeBtn.addEventListener('click', (e) => {
            e.preventDefault();
            toggleChat();
        });
    }

    document.addEventListener('click', (e) => {
        if (fabContainer && fabContainer.classList.contains('active')) {
            if (!fabContainer.contains(e.target)) {
                fabContainer.classList.remove('active');
            }
        }
    });

    // 🚀 DELETE HISTORY LOGIC
    const deleteBtn = document.getElementById('wp-ai-delete-chat');
    const modal = document.getElementById('wp-ai-confirm-modal');
    
    if (deleteBtn && modal) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            modal.classList.remove('wp-ai-hidden');
        });
    
        document.getElementById('wp-ai-cancel-del').addEventListener('click', () => {
            modal.classList.add('wp-ai-hidden'); 
        });
    
        document.getElementById('wp-ai-confirm-del').addEventListener('click', () => {
            localStorage.removeItem(STORAGE_KEY);
            localStorage.removeItem(PRE_CHAT_KEY); 
            chatBody.innerHTML = '';
            renderMessage('Chat history cleared.', 'bot', true);
            modal.classList.add('wp-ai-hidden');
            setTimeout(() => { window.location.reload(); }, 500);
        });
    }

    function scrollToBottom() { chatBody.scrollTop = chatBody.scrollHeight; }

    function saveToStorage(text, sender) {
        let history = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        history.push({ text: text, sender: sender });
        localStorage.setItem(STORAGE_KEY, JSON.stringify(history));
    }

    // 🚀 DYNAMIC QUICK ACTIONS VISIBILITY LOGIC
    function checkQuickActionsVisibility() {
        const qaWrapper = document.getElementById('wp-ai-quick-actions-wrapper');
        const qaButtons = document.querySelectorAll('.wp-ai-quick-action-btn');
        const scrollLeftBtn = document.getElementById('wp-ai-scroll-left');
        const scrollRightBtn = document.getElementById('wp-ai-scroll-right');

        if (!qaWrapper) return;
        
        const actions = wpAiChatbotData.quick_actions;
        if (!actions || actions.length === 0) {
            qaWrapper.style.display = 'none';
            return;
        }

        let preChatSubmitted = localStorage.getItem(PRE_CHAT_KEY);
        let isPreChatActive = wpAiChatbotData.pre_chat_form_id && !preChatSubmitted;
        
        // 🚀 FIX: Sirf tab check karo jab guided bubbles asal mein screen par mojud hon
        let areGuidedBubblesOnScreen = document.getElementById('wp-ai-guided-bubbles') !== null;

        qaWrapper.style.display = 'flex';

        if (isPreChatActive || areGuidedBubblesOnScreen) {
            qaWrapper.classList.add('wp-ai-disabled');
            qaButtons.forEach(btn => btn.disabled = true);
            if(scrollLeftBtn) scrollLeftBtn.disabled = true;
            if(scrollRightBtn) scrollRightBtn.disabled = true;
        } else {
            qaWrapper.classList.remove('wp-ai-disabled');
            qaButtons.forEach(btn => btn.disabled = false);
            if(scrollLeftBtn) scrollLeftBtn.disabled = false;
            if(scrollRightBtn) scrollRightBtn.disabled = false;
        }
    }

    function loadFromStorage() {
        let history = JSON.parse(localStorage.getItem(STORAGE_KEY));
        let preChatSubmitted = localStorage.getItem(PRE_CHAT_KEY);

        if (wpAiChatbotData.pre_chat_form_id && !preChatSubmitted) {
            chatBody.innerHTML = ''; 
            chatInput.disabled = true;
            sendBtn.disabled = true;
            chatInput.placeholder = "Please fill the form first...";
            renderMessage('Welcome! Please fill out this quick form before we start chatting:', 'bot', false);
            
            const msgDiv = document.createElement('div');
            msgDiv.className = 'wp-ai-message wp-ai-bot-message';
            msgDiv.innerHTML = generateFormHTML(wpAiChatbotData.pre_chat_form_id);
            const formEl = msgDiv.querySelector('form');
            if(formEl) formEl.classList.add('is-pre-chat-form');
            chatBody.appendChild(msgDiv);
            scrollToBottom();
            
        } else if (history && history.length > 0) {
            chatBody.innerHTML = ''; 
            history.forEach(msg => { renderMessage(msg.text, msg.sender, false); });
        } else {
            chatBody.innerHTML = ''; 
            renderMessage('Hi there! How can I help you today?', 'bot', true);
        }

        // 🚀 FIX: Agar user ne pehle se chat shuru kar di hai, toh guided queries dobara mat dikhao!
        let userHasChatted = history && history.some(msg => msg.sender === 'user');

        if (!userHasChatted && (!wpAiChatbotData.pre_chat_form_id || preChatSubmitted)) {
            renderGuidedQueries();
        }

        // Apply visibility check dynamically
        checkQuickActionsVisibility();
    }

    // 🚀 STRICT GUIDED FLOW RENDERER
    // function renderGuidedQueries() {
    //     const queries = wpAiChatbotData.guided_queries;
    //     if (queries && queries.length > 0) {
    //         chatInput.disabled = true;
    //         sendBtn.disabled = true; 
    //         chatInput.placeholder = "Please select an option from chat...";
            
    //         const oldContainer = document.getElementById('wp-ai-guided-bubbles');
    //         if(oldContainer) oldContainer.remove();

    //         const bubbleContainer = document.createElement('div');
    //         bubbleContainer.id = 'wp-ai-guided-bubbles';
    //         bubbleContainer.className = 'wp-ai-guided-options-container';
            
    //         let html = '';
    //         queries.forEach(query => {
    //             if (query.trim() !== '') {
    //                 html += `<button class="wp-ai-guided-btn">${query}</button>`;
    //             }
    //         });
    //         bubbleContainer.innerHTML = html;
    //         chatBody.appendChild(bubbleContainer);
    //         scrollToBottom();

    //         // Bubble Click Events
    //         bubbleContainer.querySelectorAll('.wp-ai-guided-btn').forEach(btn => {
    //             btn.addEventListener('click', function(e) {
    //                 e.preventDefault();
    //                 bubbleContainer.remove(); // 🚀 Bubble remove kiya
                    
    //                 const msg = this.textContent;
    //                 chatInput.value = msg;
                    
    //                 // 🚀 FINAL FIX: Everything completely enabled!
    //                 chatInput.disabled = false; 
    //                 sendBtn.disabled = false; 
    //                 chatInput.placeholder = "Type your message..."; // Placeholder restored
                    
    //                 checkQuickActionsVisibility(); // Quick Actions Unlocked!
                    
    //                 handleSendMessage();
    //             });
    //         });
    //     }
    // }

    // 🚀 STRICT GUIDED FLOW RENDERER (Updated for Enable/Disable Toggle)
    function renderGuidedQueries() {
        // NAYA IZAFA: Agar Backend se switch Disable hai toh code yahin rok do!
        if (wpAiChatbotData.is_guided_enabled !== '1') {
            return; 
        }

        const queries = wpAiChatbotData.guided_queries;
        if (queries && queries.length > 0) {
            chatInput.disabled = true;
            sendBtn.disabled = true; 
            chatInput.placeholder = "Please select an option from chat...";
            
            const oldContainer = document.getElementById('wp-ai-guided-bubbles');
            if(oldContainer) oldContainer.remove();

            const bubbleContainer = document.createElement('div');
            bubbleContainer.id = 'wp-ai-guided-bubbles';
            bubbleContainer.className = 'wp-ai-guided-options-container';
            
            let html = '';
            queries.forEach(query => {
                if (query.trim() !== '') {
                    html += `<button class="wp-ai-guided-btn">${query}</button>`;
                }
            });
            bubbleContainer.innerHTML = html;
            chatBody.appendChild(bubbleContainer);
            scrollToBottom();

            // Bubble Click Events
            bubbleContainer.querySelectorAll('.wp-ai-guided-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    bubbleContainer.remove(); 
                    
                    const msg = this.textContent;
                    chatInput.value = msg;
                    
                    chatInput.disabled = false; 
                    sendBtn.disabled = false; 
                    chatInput.placeholder = "Type your message..."; 
                    
                    checkQuickActionsVisibility(); 
                    
                    handleSendMessage();
                });
            });
        }
    }

    function generateFormHTML(formId) {
        const forms = wpAiChatbotData.forms;
        if(!forms || forms.length === 0) return '<p>No forms available.</p>';
        
        const form = forms.find(f => f.id === formId);
        if(!form) return '<p>Requested form not found.</p>';

        let html = `<div class="wp-ai-form-container" style="background:#f9f9f9; padding:15px; border-radius:8px; border:1px solid #ddd; margin-top:10px;">`;
        html += `<h4 style="margin-top:0; margin-bottom:10px; font-size:14px; color:#333;">${form.title}</h4>`;
        html += `<form class="wp-ai-lead-form">`;
        html += `<input type="hidden" name="wp_ai_form_title" value="${form.title}">`;
        html += `<input type="hidden" name="wp_ai_form_id" value="${form.id}">`;

        if (form.fields && Array.isArray(form.fields)) {
            form.fields.forEach((field) => {
                const req = field.required === 'yes' ? 'required' : '';
                const fieldName = field.label.replace(/[^a-zA-Z0-9 ]/g, "").replace(/\s+/g, '_').toLowerCase();

                html += `<div style="margin-bottom:10px;">`;
                html += `<label style="display:block; font-size:12px; margin-bottom:4px; color:#555;">${field.label} ${req ? '<span style="color:red;">*</span>' : ''}</label>`;
                
                if (field.type === 'textarea') {
                    html += `<textarea name="${fieldName}" ${req} style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:13px;"></textarea>`;
                } else if (field.type === 'select') {
                    html += `<select name="${fieldName}" ${req} style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:13px; background:#fff;">`;
                    html += `<option value="">Select an option...</option>`;
                    if (field.options) {
                        const opts = field.options.split(',');
                        opts.forEach(opt => {
                            const trimmedOpt = opt.trim();
                            if (trimmedOpt) html += `<option value="${trimmedOpt}">${trimmedOpt}</option>`;
                        });
                    }
                    html += `</select>`;
                } else if (field.type === 'multiselect') {
                    const opts = field.options ? field.options.split(',').map(o => o.trim()).filter(Boolean) : [];
                    const dropId = 'wp-ai-ms-' + fieldName + '-' + Math.floor(Math.random()*9999);
                    
                    html += `<div class="wp-ai-ms-wrapper" id="${dropId}-wrapper" style="position:relative;">
                                <div class="wp-ai-ms-trigger" id="${dropId}-trigger" data-target="${dropId}" style="width:100%; padding:8px 12px; border:1px solid #ccc; border-radius:6px; background:#fff; cursor:pointer; display:flex; justify-content:space-between; align-items:center; font-size:13px; color:#555; user-select:none;">
                                    <span class="wp-ai-ms-label">Select options...</span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"></polyline></svg>
                                </div>
                                <div class="wp-ai-ms-dropdown" id="${dropId}-dropdown" style="display:none; position:absolute; top:calc(100% + 4px); left:0; right:0; background:#fff; border:1px solid #ccc; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:9999; overflow:hidden;">
                                    <label style="display:flex; align-items:center; gap:8px; padding:8px 12px; background:#f8f9fa; border-bottom:1px solid #eee; cursor:pointer; font-size:12px; font-weight:600; color:#444;">
                                        <input type="checkbox" class="wp-ai-ms-selectall" data-target="${dropId}" style="width:14px; height:14px; cursor:pointer;">
                                        Select All
                                    </label>
                                    ${opts.map(opt => `<label style="display:flex; align-items:center; gap:8px; padding:8px 12px; cursor:pointer; font-size:13px; color:#333;" onmouseover="this.style.background='#f0f4ff'" onmouseout="this.style.background='transparent'">
                                        <input type="checkbox" name="${fieldName}[]" value="${opt}" class="wp-ai-ms-option" data-group="${dropId}" style="width:14px; height:14px; cursor:pointer;"> ${opt}</label>`).join('')}
                                </div>
                            </div>`;
                } else {
                    html += `<input type="${field.type}" name="${fieldName}" ${req} style="width:100%; padding:8px; border:1px solid #ccc; border-radius:4px; font-size:13px;" />`;
                }
                html += `</div>`;
            });
        }
        
        html += `<button type="submit" class="wp-ai-submit-btn" style="width:100%; background:var(--wp-ai-primary-color); color:#fff; border:none; padding:10px; border-radius:4px; cursor:pointer; font-weight:bold;">Submit</button>`;
        html += `</form></div>`;
        return html;
    }

    // function renderMessage(text, sender, save = true) {
    //     if (!chatBody) return;
    //     const msgDiv = document.createElement('div');
    //     msgDiv.className = `wp-ai-message ${sender === 'user' ? 'wp-ai-user-message' : 'wp-ai-bot-message'}`;
        
    //     if (sender === 'bot') {
    //         if (typeof marked !== 'undefined') marked.setOptions({ gfm: true, breaks: true });
    //         let htmlContent = (typeof marked !== 'undefined') ? marked.parse(text) : text.replace(/\n/g, '<br>');
    //         htmlContent = htmlContent.replace(/(?<!src=['"])(https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif))/gi, (match) => {
    //             return `<img src="${match}" style="max-width:100px; height:auto; border-radius:6px; display:block; margin:5px 0;">`;
    //         });
    //         const formRegex = /\[SHOW_FORM_([a-zA-Z0-9_]+)\]/;
    //         const match = text.match(formRegex);
    //         if (match) {
    //             msgDiv.innerHTML = generateFormHTML(match[1]);
    //         } else {
    //             msgDiv.innerHTML = htmlContent;
    //         }
    //     } else {
    //         msgDiv.textContent = text;
    //     }
    //     chatBody.appendChild(msgDiv);
    //     scrollToBottom();
    //     if (save) saveToStorage(text, sender);
    // }

    // function renderMessage(text, sender, save = true) {
    //     if (!chatBody) return;
    //     const msgDiv = document.createElement('div');
    //     msgDiv.className = `wp-ai-message ${sender === 'user' ? 'wp-ai-user-message' : 'wp-ai-bot-message'}`;
        
    //     if (sender === 'bot') {
    //         if (typeof marked !== 'undefined') marked.setOptions({ gfm: true, breaks: true });
    //         let htmlContent = (typeof marked !== 'undefined') ? marked.parse(text) : text.replace(/\n/g, '<br>');
    //         htmlContent = htmlContent.replace(/(?<!src=['"])(https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif))/gi, (match) => {
    //             return `<img src="${match}" style="max-width:100px; height:auto; border-radius:6px; display:block; margin:5px 0;">`;
    //         });
    //         const formRegex = /\[SHOW_FORM_([a-zA-Z0-9_]+)\]/;
    //         const match = text.match(formRegex);
    //         if (match) {
    //             msgDiv.innerHTML = generateFormHTML(match[1]);
    //         } else {
    //             msgDiv.innerHTML = htmlContent;
    //         }
    //     } else {
    //         msgDiv.textContent = text;
    //     }

    //     // NAYA IZAFA: Copy Button & Logic
    //     const copyBtn = document.createElement('button');
    //     copyBtn.className = 'wp-ai-copy-btn';
    //     copyBtn.title = 'Copy Message';
    //     // Default Copy Icon
    //     copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
        
    //     copyBtn.addEventListener('click', (e) => {
    //         e.preventDefault();
            
    //         // Text copy karte waqt form shortcodes ko remove kar do taake kachra copy na ho
    //         let textToCopy = text.replace(/\[SHOW_FORM_[a-zA-Z0-9_]+\]/g, '').trim();
            
    //         navigator.clipboard.writeText(textToCopy).then(() => {
    //             // Copy hone par Green Tick (Success Icon) dikhao
    //             copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
                
    //             // 2 second baad wapas normal copy icon kar do
    //             setTimeout(() => {
    //                 copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
    //             }, 2000);
    //         });
    //     });
    //     msgDiv.appendChild(copyBtn);
    //     // ---------------------------------

    //     chatBody.appendChild(msgDiv);
    //     scrollToBottom();
    //     if (save) saveToStorage(text, sender);
    // }

    function renderMessage(text, sender, save = true) {
        if (!chatBody) return;

        // NAYA IZAFA: Message aur button ko ek sath rakhne ke liye Row (Wrapper)
        const rowDiv = document.createElement('div');
        rowDiv.className = `wp-ai-message-row wp-ai-message-row-${sender}`;

        // Asal Message Bubble
        const msgDiv = document.createElement('div');
        msgDiv.className = `wp-ai-message wp-ai-${sender}-message`;
        
        if (sender === 'bot') {
            if (typeof marked !== 'undefined') marked.setOptions({ gfm: true, breaks: true });
            let htmlContent = (typeof marked !== 'undefined') ? marked.parse(text) : text.replace(/\n/g, '<br>');
            htmlContent = htmlContent.replace(/(?<!src=['"])(https?:\/\/[^\s]+?\.(?:jpg|jpeg|png|gif))/gi, (match) => {
                return `<img src="${match}" style="max-width:100px; height:auto; border-radius:6px; display:block; margin:5px 0;">`;
            });
            const formRegex = /\[SHOW_FORM_([a-zA-Z0-9_]+)\]/;
            const match = text.match(formRegex);
            if (match) {
                msgDiv.innerHTML = generateFormHTML(match[1]);
            } else {
                msgDiv.innerHTML = htmlContent;
            }
        } else {
            msgDiv.textContent = text;
        }

        // Copy Button Ka Logic
        const copyBtn = document.createElement('button');
        copyBtn.className = 'wp-ai-copy-btn';
        copyBtn.title = 'Copy Message';
        copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
        
        copyBtn.addEventListener('click', (e) => {
            e.preventDefault();
            let textToCopy = text.replace(/\[SHOW_FORM_[a-zA-Z0-9_]+\]/g, '').trim();
            navigator.clipboard.writeText(textToCopy).then(() => {
                copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="#4ade80" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>`;
                setTimeout(() => {
                    copyBtn.innerHTML = `<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg>`;
                }, 2000);
            });
        });

        // SET ALIGNMENT BASED ON SENDER
        if (sender === 'user') {
            rowDiv.appendChild(copyBtn); // User: Copy button left par
            rowDiv.appendChild(msgDiv);  // User: Message right par
        } else {
            rowDiv.appendChild(msgDiv);  // Bot: Message left par
            rowDiv.appendChild(copyBtn); // Bot: Copy button right par
        }

        chatBody.appendChild(rowDiv);
        scrollToBottom();
        if (save) saveToStorage(text, sender);
    }

    // Form Submission
    document.addEventListener('submit', async function(e) {
        if (e.target && e.target.classList.contains('wp-ai-lead-form')) {
            e.preventDefault(); 
            const form = e.target;
            const formData = new FormData(form);
            const dataObj = {};
            formData.forEach(function(value, key) {
                if (key.endsWith('[]')) {
                    const cleanKey = key.slice(0, -2);
                    if (!dataObj[cleanKey]) dataObj[cleanKey] = [];
                    dataObj[cleanKey].push(value);
                } else {
                    dataObj[key] = value;
                }
            });
            
            const submitBtn = form.querySelector('.wp-ai-submit-btn');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Submitting...';
            submitBtn.disabled = true;

            try {
                const response = await fetch(wpAiChatbotData.form_submit_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpAiChatbotData.nonce },
                    body: JSON.stringify({ form_data: dataObj })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const formTitle = dataObj['wp_ai_form_title'] ? dataObj['wp_ai_form_title'] : 'Form';
                    const simpleSuccessHTML = `<p style="text-align:center; font-size:14px; color:green; font-weight:bold; margin:0; padding:10px;">[${formTitle} Submitted Successfully]</p>`;
                    form.innerHTML = simpleSuccessHTML;

                    setTimeout(() => { renderMessage(data.message, 'bot', true); }, 500);
                    
                    if (form.classList.contains('is-pre-chat-form')) {
                        localStorage.setItem(PRE_CHAT_KEY, 'true'); 
                        
                        // Default open karo, renderGuidedQueries isko dobara block kar dega agar zaroorat hui
                        chatInput.disabled = false; 
                        sendBtn.disabled = false; 
                        chatInput.placeholder = "Type your message..."; 
                        
                        renderGuidedQueries();
                        checkQuickActionsVisibility();

                    } else {
                        let history = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
                        for (let i = history.length - 1; i >= 0; i--) {
                            if (history[i].sender === 'bot' && history[i].text.includes('[SHOW_FORM_')) {
                                history[i].text = simpleSuccessHTML; 
                                break;
                            }
                        }
                        localStorage.setItem(STORAGE_KEY, JSON.stringify(history)); 
                    }
                } else {
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                    alert('Error: Could not save details.');
                }
            } catch (error) {
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
                alert('Network Error! Please try again.');
            }
        }
    });

    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'wp-ai-typing-indicator';
        typingDiv.id = 'wp-ai-typing';
        typingDiv.innerHTML = `<div class="wp-ai-dot"></div><div class="wp-ai-dot"></div><div class="wp-ai-dot"></div>`;
        chatBody.appendChild(typingDiv);
        scrollToBottom();
    }

    function removeTypingIndicator() {
        const typingDiv = document.getElementById('wp-ai-typing');
        if (typingDiv) typingDiv.remove();
    }

    async function handleSendMessage() {
        const message = chatInput.value.trim();
        if (message === '') return;

        renderMessage(message, 'user', true);
        chatInput.value = '';
        chatInput.disabled = true;
        sendBtn.disabled = true;
        showTypingIndicator();

        let chatHistory = JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
        let recentHistory = chatHistory.slice(-6);

        try {
            const response = await fetch(wpAiChatbotData.rest_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wpAiChatbotData.nonce },
                body: JSON.stringify({ message: message, history: recentHistory })
            });

            const data = await response.json();
            removeTypingIndicator();

            if (response.ok && data.success) {
                renderMessage(data.reply, 'bot', true);
            } else {
                renderMessage('Sorry, I encountered an error.', 'bot', false);
            }
        } catch (error) {
            removeTypingIndicator();
            renderMessage('Network error.', 'bot', false);
        } finally {
            chatInput.disabled = false;
            sendBtn.disabled = false;
            chatInput.focus();
        }
    }

    // Scroll Logic
    function enableDragToScroll(elementId) {
        const container = document.getElementById(elementId);
        if (!container) return;
        let isDown = false, startX, startY, scrollLeft, scrollTop;

        container.addEventListener('mousedown', (e) => {
            isDown = true; container.classList.add('wp-ai-dragging');
            startX = e.pageX - container.offsetLeft; startY = e.pageY - container.offsetTop;
            scrollLeft = container.scrollLeft; scrollTop = container.scrollTop;
        });
        container.addEventListener('mouseleave', () => { isDown = false; container.classList.remove('wp-ai-dragging'); });
        container.addEventListener('mouseup', () => { isDown = false; container.classList.remove('wp-ai-dragging'); });
        container.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault(); e.stopPropagation();
            if (container.scrollWidth > container.clientWidth) container.scrollLeft = scrollLeft - ((e.pageX - container.offsetLeft) - startX) * 2;
            if (container.scrollHeight > container.clientHeight) container.scrollTop = scrollTop - ((e.pageY - container.offsetTop) - startY) * 2;
        });
        container.addEventListener('wheel', (e) => { e.stopPropagation(); }, { passive: true });
    }

    function loadQuickActions() {
        const wrapper = document.getElementById('wp-ai-quick-actions-wrapper');
        const container = document.getElementById('wp-ai-quick-actions');
        const actions = wpAiChatbotData.quick_actions;
        
        if(!actions || actions.length === 0) { 
            if (wrapper) wrapper.style.display = 'none'; 
            return; 
        }
        
        if (wrapper) wrapper.style.display = 'flex';
        
        let html = '';
        actions.forEach(action => { html += `<button type="button" class="wp-ai-quick-action-btn" data-form="${action.form_id}">${action.label}</button>`; });
        container.innerHTML = html;

        container.querySelectorAll('.wp-ai-quick-action-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if(this.disabled || wrapper.classList.contains('wp-ai-disabled')) return;
                
                e.preventDefault(); e.stopPropagation();
                const formId = this.getAttribute('data-form');
                const label = this.textContent;
                renderMessage(label, 'user', true);
                const msgDiv = document.createElement('div');
                msgDiv.className = 'wp-ai-message wp-ai-bot-message';
                msgDiv.innerHTML = generateFormHTML(formId);
                chatBody.appendChild(msgDiv);
                scrollToBottom();
                saveToStorage(`[SHOW_FORM_${formId}]`, 'bot');
            });
        });

        const scrollLeftBtn = document.getElementById('wp-ai-scroll-left');
        const scrollRightBtn = document.getElementById('wp-ai-scroll-right');
        
        if(scrollLeftBtn) scrollLeftBtn.addEventListener('click', () => { 
            if(!scrollLeftBtn.disabled) container.scrollBy({ left: -150, behavior: 'smooth' }); 
        });
        if(scrollRightBtn) scrollRightBtn.addEventListener('click', () => { 
            if(!scrollRightBtn.disabled) container.scrollBy({ left: 150, behavior: 'smooth' }); 
        });
    }

    // Init Calls
    loadFromStorage();
    loadQuickActions();
    
    // 🚀 Chat body par ab sirf wheel chalega, drag nahi (taake text copy ho sakay)
    enableWheelScroll('wp-ai-chat-body');      
    
    // Quick actions (horizontal buttons) par drag theek hai
    enableDragToScroll('wp-ai-quick-actions');
    
    if(sendBtn) sendBtn.addEventListener('click', handleSendMessage);
    if(chatInput) chatInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') { e.preventDefault(); handleSendMessage()}});

    // Custom Select Dropdowns
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.wp-ai-ms-trigger');
        if (trigger) {
            e.stopPropagation();
            const dropId = trigger.getAttribute('data-target');
            const dropdown = document.getElementById(dropId + '-dropdown');
            if (!dropdown) return;
            const isOpen = dropdown.style.display !== 'none';
            document.querySelectorAll('.wp-ai-ms-dropdown').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.wp-ai-ms-trigger').forEach(t => t.style.borderColor = '#ccc');
            if (!isOpen) { dropdown.style.display = 'block'; trigger.style.borderColor = 'var(--wp-ai-primary-color)'; }
            return;
        }
        if (!e.target.closest('.wp-ai-ms-wrapper')) {
            document.querySelectorAll('.wp-ai-ms-dropdown').forEach(d => d.style.display = 'none');
            document.querySelectorAll('.wp-ai-ms-trigger').forEach(t => t.style.borderColor = '#ccc');
        }
    });

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('wp-ai-ms-selectall')) {
            const dropId = e.target.getAttribute('data-target');
            document.querySelectorAll(`.wp-ai-ms-option[data-group="${dropId}"]`).forEach(cb => { cb.checked = e.target.checked; });
            updateMultiselectLabel(dropId);
        }
        if (e.target.classList.contains('wp-ai-ms-option')) {
            const dropId = e.target.getAttribute('data-group');
            updateMultiselectLabel(dropId);
            const allOpts = document.querySelectorAll(`.wp-ai-ms-option[data-group="${dropId}"]`);
            const allChecked = Array.from(allOpts).every(cb => cb.checked);
            const selectAll = document.querySelector(`.wp-ai-ms-selectall[data-target="${dropId}"]`);
            if (selectAll) selectAll.checked = allChecked;
        }
    });

    function updateMultiselectLabel(dropId) {
        const checked = document.querySelectorAll(`.wp-ai-ms-option[data-group="${dropId}"]:checked`);
        const trigger = document.getElementById(dropId + '-trigger');
        if (!trigger) return;
        const label = trigger.querySelector('.wp-ai-ms-label');
        if (!label) return;
        if (checked.length === 0) { label.textContent = 'Select options...'; label.style.color = '#999'; } 
        else { label.textContent = Array.from(checked).map(c => c.value).join(', '); label.style.color = '#333'; }
    }

    // NAYA IZAFA: Sirf Mouse Wheel Scrolling ke liye (Text selection kharab nahi karega)
    function enableWheelScroll(elementId) {
        const container = document.getElementById(elementId);
        if (!container) return;
        container.addEventListener('wheel', (e) => { 
            e.stopPropagation(); // Peechay wali website ko scroll hone se rokega
        }, { passive: true });
    }
});