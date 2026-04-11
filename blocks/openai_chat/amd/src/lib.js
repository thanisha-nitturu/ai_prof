// Add semicolons to the global var declarations.
var questionString = 'Ask a question...';
var errorString = 'An error occurred! Please try again later.';

// Declare chatData at the top level so it's accessible to all functions.
let chatData = null;

// Variables for user/assistant names - assuming they are passed via M.block_openai_chat.init
// or fetched as strings by the AMD module system. If they are configured in the block's
// settings, they should be passed in the 'data' object of the init function.
// For now, let's declare them as module-scoped variables and set them in init.
let userName = "User"; // Default fallback
let assistantName = "Assistant"; // Default fallback

export const init = (data) => {
    const blockId = data['blockId'];
    const api_type = data['api_type'];
    const persistConvo = data['persistConvo'];

    // Assign userName and assistantName from data if provided, or use defaults.
    if (data.strings && data.strings.username) {
        userName = data.strings.username;
    }
    if (data.strings && data.strings.assistantname) {
        assistantName = data.strings.assistantname;
    }

    // Initialize local data storage if necessary
    // If a thread ID exists for this block, make an API request to get existing messages
    if (api_type === 'assistant') {
        chatData = localStorage.getItem("block_openai_chat_data");
        if (chatData) {
            chatData = JSON.parse(chatData);
            if (chatData[blockId] && chatData[blockId]['threadId'] && persistConvo === "1") {
                fetch(`${M.cfg.wwwroot}/blocks/openai_chat/api/thread.php?thread_id=${chatData[blockId]['threadId']}`)
                .then(response => response.json())
                .then(data => {
                    for (let message of data) {
                        addToChatLog(message.role === 'user' ? 'user' : 'bot', message.message);
                    }
                })
                .catch((_error) => { // Corrected syntax: `catch (_error) {`
                    void _error; // Explicitly mark _error as unused
                    chatData[blockId] = {};
                    localStorage.setItem("block_openai_chat_data", JSON.stringify(chatData));
                });
            } else {
                // The block ID doesn't exist in the chat data object, so let's create it
                chatData[blockId] = {};
            }
        } else {
            // We don't even have a chat data object, so we'll create one
            chatData = {[blockId]: {}};
        }
        localStorage.setItem("block_openai_chat_data", JSON.stringify(chatData));
    }

    // Prevent sidebar from closing when osk pops up (hack for MDL-77957)
    window.addEventListener('resize', (_event) => {
        _event.stopImmediatePropagation();
    }, true);


    document.querySelector('#openai_input').addEventListener('keyup', e => {
        if (e.which === 13 && e.target.value !== "") {
            addToChatLog('user', e.target.value);
            createCompletion(e.target.value, blockId, api_type);
            e.target.value = '';
        }
    });

    document.querySelector('.block_openai_chat #go').addEventListener('click', () => {
        const input = document.querySelector('#openai_input');
        if (input.value !== "") {
            addToChatLog('user', input.value);
            createCompletion(input.value, blockId, api_type);
            input.value = '';
        }
    });

    document.querySelector('.block_openai_chat #refresh').addEventListener('click', () => {
        clearHistory(blockId);
    });

    document.querySelector('.block_openai_chat #popout').addEventListener('click', () => {
        if (document.querySelector('.drawer.drawer-right')) {
            document.querySelector('.drawer.drawer-right').style.zIndex = '1041';
        }
        document.querySelector('.block_openai_chat').classList.toggle('expanded');
    });

    require(['core/str'], function(str) {
        var strings = [
            {
                key: 'askaquestion',
                component: 'block_openai_chat'
            },
            {
                key: 'erroroccurred',
                component: 'block_openai_chat'
            },
        ];
        str.get_strings(strings).then((results) => {
            questionString = results[0];
            errorString = results[1];
        });
    });
};

/**
 * Add a message to the chat UI
 * @param {string} type Which side of the UI the message should be on. Can be "user" or "bot"
 * @param {string} message The text of the message to add
 */
const addToChatLog = (type, message) => {
    let messageContainer = document.querySelector('#openai_chat_log');

    const messageElem = document.createElement('div');
    messageElem.classList.add('openai_message');
    for (let className of type.split(' ')) {
        messageElem.classList.add(className);
    }

    const messageText = document.createElement('span');
    messageText.innerHTML = message;
    messageElem.append(messageText);

    messageContainer.append(messageElem);
    if (messageText.offsetWidth) {
        messageElem.style.width = (messageText.offsetWidth + 40) + "px";
    }
    messageContainer.scrollTop = messageContainer.scrollHeight;
    messageContainer.closest('.block_openai_chat > div').scrollTop = messageContainer.scrollHeight;
};

/**
 * Clears the thread ID from local storage and removes the messages from the UI in order to refresh the chat
 * @param {string} blockId The ID of the block whose history is being cleared.
 */
const clearHistory = (blockId) => {
    chatData = localStorage.getItem("block_openai_chat_data");
    if (chatData) {
        chatData = JSON.parse(chatData);
        if (chatData[blockId]) {
            chatData[blockId] = {};
            localStorage.setItem("block_openai_chat_data", JSON.stringify(chatData));
        }
    }
    document.querySelector('#openai_chat_log').innerHTML = "";
};

/**
 * Makes an API request to get a completion from GPT-3, and adds it to the chat log
 * @param {string} message The text to get a completion for
 * @param {int} blockId The ID of the block this message is being sent from -- used to override settings if necessary
 * @param {string} api_type "assistant" | "chat" The type of API to use
 */
const createCompletion = (message, blockId, api_type) => {
    let threadId = null;

    // If the type is assistant, attempt to fetch a thread ID
    if (api_type === 'assistant') {
        chatData = localStorage.getItem("block_openai_chat_data");
        if (chatData) {
            chatData = JSON.parse(chatData);
            if (chatData[blockId]) {
                threadId = chatData[blockId]['threadId'] || null;
            }
        } else {
            // create the chat data item if necessary
            chatData = {[blockId]: {}};
        }
    }

    const history = buildTranscript();

    document.querySelector('.block_openai_chat #control_bar').classList.add('disabled');
    document.querySelector('#openai_input').classList.remove('error');
    document.querySelector('#openai_input').placeholder = questionString;
    document.querySelector('#openai_input').blur();
    addToChatLog('bot loading', '...');

    fetch(`${M.cfg.wwwroot}/blocks/openai_chat/api/completion.php`, {
        method: 'POST',
        body: JSON.stringify({
            message: message,
            history: history,
            blockId: blockId,
            threadId: threadId
        })
    })
    .then(response => {
        let messageContainer = document.querySelector('#openai_chat_log');
        messageContainer.removeChild(messageContainer.lastElementChild);
        document.querySelector('.block_openai_chat #control_bar').classList.remove('disabled');

        if (!response.ok) {
            throw Error(response.statusText);
        } else {
            return response.json();
        }
    })
    .then(data => {
        try {
            addToChatLog('bot', data.message);
            if (data.thread_id) {
                chatData[blockId]['threadId'] = data.thread_id;
                localStorage.setItem("block_openai_chat_data", JSON.stringify(chatData));
            }
        } catch (_error) { // Corrected syntax: `catch (_error) {`
            void _error; // Explicitly mark _error as unused
            addToChatLog('bot', data.error && data.error.message ? data.error.message : errorString);
        }
        document.querySelector('#openai_input').focus();
    })
    .catch((_error) => { // Corrected syntax: `catch (_error) {`
        void _error; // Explicitly mark _error as unused
        document.querySelector('#openai_input').classList.add('error');
        document.querySelector('#openai_input').placeholder = errorString;
    });
};

/**
 * Using the existing messages in the chat history, create a string that can be used to aid completion
 * @return {JSONObject} A transcript of the conversation up to this point
 */
const buildTranscript = () => {
    let transcript = [];
    document.querySelectorAll('.openai_message').forEach((message, index) => {
        if (index === document.querySelectorAll('.openai_message').length - 1) {
            return;
        }

        let user = userName;
        if (message.classList.contains('bot')) {
            user = assistantName;
        }
        transcript.push({"user": user, "message": message.innerText});
    });

    return transcript;
};
