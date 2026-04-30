// Отримуємо налаштування з API
var hideMap = {};
var customBbcodes = [];

// Ініціалізація при завантаженні
function init() {
    // Заповнюємо hideMap з налаштувань розширення
    var keys = ['fa-bold','fa-italic','fa-underline','fa-link','fa-image',
                'fa-code','fa-quote-left','fa-strikethrough','fa-heading',
                'fa-list-ul','fa-list-ol','fa-exclamation-triangle','fa-at','fa-eye'];
    
    var settings = app.forum.attribute('olleksi-bbcodes');
    // settings — це об'єкт з усіма serializeToForum ключами
    
    if (settings) {
        hideMap = {
            'fa-bold': settings.hide_bold,
            'fa-italic': settings.hide_italic,
            'fa-underline': settings.hide_underline,
            'fa-link': settings.hide_link,
            'fa-image': settings.hide_image,
            'fa-code': settings.hide_code,
            'fa-quote-left': settings.hide_quote,
            'fa-strikethrough': settings.hide_strike,
            'fa-heading': settings.hide_header,
            'fa-list-ul': settings.hide_list,
            'fa-list-ol': settings.hide_list,
            'fa-exclamation-triangle': settings.hide_spoiler,
            'fa-at': settings.hide_mention,
            'fa-eye': settings.hide_preview
        };
        
        try {
            customBbcodes = JSON.parse(settings.custom_bbcodes || '[]');
        } catch(e) {
            customBbcodes = [];
        }
    }
    
    // Запускаємо обробку тулбарів
    setTimeout(processAllToolbars, 100);
    
    // Спостерігаємо за змінами DOM
    var observer = new MutationObserver(function() {
        setTimeout(processAllToolbars, 50);
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
    
    // Періодична перевірка
    setInterval(function() {
        var unprocessed = document.querySelectorAll(".TextEditor-controls:not([data-olleksi-done])");
        if (unprocessed.length > 0) {
            processAllToolbars();
        }
        
        document.querySelectorAll(".TextEditor-controls[data-olleksi-done]").forEach(function(tb) {
            if (tb.offsetParent === null) {
                tb.removeAttribute("data-olleksi-done");
            }
        });
    }, 200);
    
    // Слухаємо кліки по кнопках
    document.addEventListener("click", function(e) {
        if (e.target.closest(".Button--primary, .Post-edit, .Post-comment, .Button--link, .Post-quoteButton")) {
            setTimeout(processAllToolbars, 100);
            setTimeout(processAllToolbars, 300);
        }
    }, true);
}

function insertAtCursor(textarea, open, close) {
    if (!textarea) return;
    var start = textarea.selectionStart;
    var end = textarea.selectionEnd;
    var sel = textarea.value.substring(start, end);
    textarea.value = textarea.value.substring(0, start) + open + sel + close + textarea.value.substring(end);
    textarea.focus();
    textarea.selectionStart = start + open.length;
    textarea.selectionEnd = start + open.length + sel.length;
    textarea.dispatchEvent(new Event("input", { bubbles: true }));
}

function hideStandardButtons(toolbar) {
    if (!toolbar) return;
    
    toolbar.querySelectorAll("button, .Button").forEach(function(button) {
        var icon = button.querySelector(".icon, .fas, .far, .fal");
        if (!icon) return;
        
        for (var iconClass in hideMap) {
            if (hideMap[iconClass] && icon.classList.contains(iconClass)) {
                button.style.setProperty("display", "none", "important");
                button.setAttribute("data-olleksi-hidden", "true");
                return;
            }
        }
    });
}

function addCustomButtons(toolbar) {
    if (!toolbar) return;
    
    customBbcodes.forEach(function(bb) {
        if (!bb.visible) return;
        
        var btnId = "olleksi-custom-btn-" + bb.name.replace(/[^a-zA-Z0-9]/g, "-");
        if (toolbar.querySelector("#" + btnId)) return;
        
        var btn = document.createElement("button");
        btn.id = btnId;
        btn.className = "Button Button--icon hasIcon";
        btn.title = bb.tooltip || bb.name;
        btn.type = "button";
        btn.setAttribute("aria-label", bb.tooltip || bb.name);
        btn.innerHTML = '<i class="icon fas ' + bb.icon + '" aria-hidden="true"></i>';
        
        btn.addEventListener("click", function(e) {
            e.preventDefault();
            e.stopPropagation();
            var textarea = toolbar.closest(".TextEditor")?.querySelector("textarea");
            if (textarea) {
                insertAtCursor(textarea, bb.open, bb.close);
            }
        });
        
        toolbar.appendChild(btn);
    });
}

// --- ГАЛЕРЕЯ ТАРО ---
var galleryModal = null;

window.insertToEditor = function(code) {
    var textarea = document.querySelector("textarea.FormControl.Composer-flexible.TextEditor-editor");
    if (textarea) {
        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        textarea.value = value.substring(0, start) + code + value.substring(end);
        textarea.focus();
        textarea.dispatchEvent(new Event("input", { bubbles: true }));
    }
};

window.showGalleryModal = function() {
    if (!galleryModal) {
        galleryModal = document.createElement("div");
        galleryModal.id = "galleryModal";
        galleryModal.className = "gallery-modal";
        galleryModal.innerHTML = '' +
            '<div class="gallery-modal-content">' +
                '<div class="gallery-modal-header">' +
                    '<h3>📷 Галерея Таро</h3>' +
                    '<button class="gallery-close" id="galleryModalClose">×</button>' +
                '</div>' +
                '<div class="gallery-modal-body">' +
                    '<iframe src="https://test.tarot.pp.ua/0/gallery.html" style="width:100%;height:100%;border:none;"></iframe>' +
                '</div>' +
            '</div>';
        document.body.appendChild(galleryModal);
        
        document.getElementById("galleryModalClose").onclick = function() {
            galleryModal.style.display = "none";
        };
        galleryModal.onclick = function(e) {
            if (e.target === galleryModal) galleryModal.style.display = "none";
        };
    }
    galleryModal.style.display = "flex";
};

window.addEventListener("message", function(event) {
    if (event.data && event.data.type === "insertCard") {
        window.insertToEditor(event.data.bbcode);
        if (galleryModal) galleryModal.style.display = "none";
    }
});

function addGalleryButtonToToolbar(toolbar) {
    if (!toolbar || toolbar.querySelector("#galleryBtn")) return;
    
    var btn = document.createElement("button");
    btn.id = "galleryBtn";
    btn.className = "Button Button--icon hasIcon";
    btn.title = "Галерея Таро";
    btn.type = "button";
    btn.setAttribute("aria-label", "Галерея Таро");
    btn.innerHTML = '<i class="icon fas fa-images" aria-hidden="true"></i>';
    btn.onclick = function(e) {
        e.preventDefault();
        window.showGalleryModal();
    };
    toolbar.appendChild(btn);
}
// --- КІНЕЦЬ ГАЛЕРЕЇ ---

function processToolbar(toolbar) {
    if (!toolbar || toolbar.hasAttribute("data-olleksi-done")) return;
    toolbar.setAttribute("data-olleksi-done", "true");
    
    hideStandardButtons(toolbar);
    addCustomButtons(toolbar);
    addGalleryButtonToToolbar(toolbar);
}

function processAllToolbars() {
    document.querySelectorAll(".TextEditor-controls:not([data-olleksi-done])").forEach(processToolbar);
    
    document.querySelectorAll(".TextEditor-controls[data-olleksi-done]").forEach(function(toolbar) {
        toolbar.querySelectorAll("button:not([data-olleksi-hidden]):not([id^=olleksi-custom-btn-]):not(#galleryBtn)").forEach(function(btn) {
            // нові кнопки — перевіряємо чи треба приховати
        });
    });
}

// Додаємо CSS стилі для галереї
var styleEl = document.createElement("style");
styleEl.textContent = '' +
'.gallery-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99999;justify-content:center;align-items:center}' +
'.gallery-modal-content{background:white;width:90%;max-width:1200px;height:80%;max-height:800px;border-radius:10px;display:flex;flex-direction:column;box-shadow:0 5px 20px rgba(0,0,0,0.3)}' +
'.gallery-modal-header{padding:15px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center}' +
'.gallery-modal-header h3{margin:0;font-size:18px}' +
'.gallery-close{background:none;border:none;font-size:28px;cursor:pointer;width:35px;height:35px;border-radius:50%;display:flex;align-items:center;justify-content:center}' +
'.gallery-close:hover{background:#f0f0f0}' +
'.gallery-modal-body{flex:1;overflow:auto;padding:0}' +
'.gallery-modal-body iframe{width:100%;height:100%;border:none}' +
'@media(max-width:768px){.gallery-modal-content{width:100%;height:85%;max-height:85%;border-radius:15px 15px 0 0}}';
document.head.appendChild(styleEl);

// Запуск
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(init, 200);
    });
} else {
    setTimeout(init, 200);
}
