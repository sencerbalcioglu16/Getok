/* Okçuluk Amatör Ligi - Frontend JS */
// Basit metin editör toolbarı: textarea'lara basit BBCode benzeri butonlar ekle
(function(){
    document.querySelectorAll('textarea.editor').forEach(function(ta){
        // Butonları ekleyelim (kalın, italik, link, görsel)
        const wrap = document.createElement('div');
        wrap.className = 'editor-toolbar';
        wrap.style.cssText = 'margin:4px 0;display:flex;gap:4px;flex-wrap:wrap;';
        const tools = [
            {l:'B', t:'<strong>', s:'</strong>', tip:'Kalın'},
            {l:'I', t:'<em>',    s:'</em>',    tip:'İtalik'},
            {l:'U', t:'<u>',     s:'</u>',     tip:'Altı çizili'},
            {l:'H2',t:'<h3>',    s:'</h3>',    tip:'Başlık'},
            {l:'P', t:'<p>',     s:'</p>',     tip:'Paragraf'},
            {l:'UL',t:'<ul><li>',s:'</li></ul>',tip:'Liste'},
            {l:'Link',t:'<a href="" target="_blank">', s:'</a>', tip:'Link'},
        ];
        tools.forEach(function(t){
            const b = document.createElement('button');
            b.type = 'button';
            b.textContent = t.l;
            b.title = t.tip;
            b.style.cssText = 'padding:4px 8px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:4px;cursor:pointer;font-size:12px;';
            b.onclick = function(e){
                e.preventDefault();
                wrapSel(ta, t.t, t.s);
            };
            wrap.appendChild(b);
        });
        // Görsel ekleme yardımcısı
        const imgBtn = document.createElement('button');
        imgBtn.type = 'button';
        imgBtn.textContent = '🖼 URL ile Görsel';
        imgBtn.style.cssText = 'padding:4px 8px;border:1px solid #cbd5e1;background:#f8fafc;border-radius:4px;cursor:pointer;font-size:12px;';
        imgBtn.onclick = function(e){
            e.preventDefault();
            const url = prompt('Görsel URL adresi:');
            if (url) wrapSel(ta, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="" style="max-width:100%">', '');
        };
        wrap.appendChild(imgBtn);
        ta.parentNode.insertBefore(wrap, ta);
    });

    function wrapSel(ta, open, close){
        const s = ta.selectionStart, e = ta.selectionEnd;
        const sel = ta.value.substring(s, e) || 'metin';
        const text = open + sel + close;
        ta.value = ta.value.substring(0, s) + text + ta.value.substring(e);
        ta.focus();
        ta.selectionStart = s + open.length;
        ta.selectionEnd   = s + open.length + sel.length;
    }

    // Form gönderimlerinde iki kez tıklamayı engelle
    document.querySelectorAll('form').forEach(function(f){
        f.addEventListener('submit', function(){
            const btn = f.querySelector('button[type=submit]');
            if (btn) { btn.disabled = true; btn.textContent = 'İşleniyor...'; }
        });
    });
})();
