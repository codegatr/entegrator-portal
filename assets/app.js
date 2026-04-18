/* CODEGA e-Fatura Portal — app.js v1 */
(function(){
'use strict';

// ═══ Fatura satır düzenleyici (fatura/yeni.php) ═══════════
var lineEditor = document.getElementById('line-editor');
if (lineEditor) {
  var lineIndex = 0;

  function addLine(data) {
    data = data || {};
    lineIndex++;
    var row = document.createElement('div');
    row.className = 'line-row';
    row.innerHTML =
      '<input type="hidden" name="lines['+lineIndex+'][sira]" value="'+lineIndex+'">' +
      '<div style="text-align:center;font-weight:700;color:#64748b">'+lineIndex+'</div>' +
      '<input type="text" name="lines['+lineIndex+'][urun_adi]" placeholder="Ürün/hizmet adı" required value="'+escAttr(data.urun_adi||'')+'">' +
      '<input type="number" name="lines['+lineIndex+'][miktar]" step="0.00000001" min="0.00000001" placeholder="Miktar" value="'+(data.miktar||1)+'" class="ln-qty" required>' +
      '<select name="lines['+lineIndex+'][birim_kodu]" class="ln-unit">' +
        '<option value="C62">Adet</option><option value="KGM">Kg</option><option value="LTR">Lt</option>' +
        '<option value="MTR">Metre</option><option value="MTK">m²</option><option value="HUR">Saat</option>' +
        '<option value="DAY">Gün</option><option value="SET">Set</option><option value="BX">Kutu</option><option value="PK">Paket</option>' +
      '</select>' +
      '<input type="number" name="lines['+lineIndex+'][birim_fiyat]" step="0.0001" min="0" placeholder="Birim fiyat" value="'+(data.birim_fiyat||'')+'" class="ln-price" required>' +
      '<input type="number" name="lines['+lineIndex+'][iskonto]" step="0.01" min="0" placeholder="İskonto" value="'+(data.iskonto||0)+'" class="ln-disc">' +
      '<select name="lines['+lineIndex+'][kdv_oran]" class="ln-vat">' +
        '<option value="1">%1</option><option value="10">%10</option><option value="20" selected>%20</option>' +
      '</select>' +
      '<button type="button" class="line-rm" title="Sil"><i class="fas fa-times"></i></button>';
    lineEditor.appendChild(row);
    row.querySelector('.line-rm').addEventListener('click', function(){ row.remove(); updateTotals(); });
    row.querySelectorAll('input,select').forEach(function(el){
      el.addEventListener('input', updateTotals);
      el.addEventListener('change', updateTotals);
    });
    updateTotals();
  }

  function updateTotals() {
    var matrah = 0, kdv = 0;
    lineEditor.querySelectorAll('.line-row').forEach(function(row){
      var q = parseFloat(row.querySelector('.ln-qty').value) || 0;
      var p = parseFloat(row.querySelector('.ln-price').value) || 0;
      var d = parseFloat(row.querySelector('.ln-disc').value) || 0;
      var v = parseFloat(row.querySelector('.ln-vat').value) || 0;
      var base = Math.round((q * p - d) * 100) / 100;
      if (base < 0) base = 0;
      var tax  = Math.round(base * v / 100 * 100) / 100;
      matrah += base;
      kdv    += tax;
    });
    var toplam = matrah + kdv;
    var el = document.getElementById('tot-matrah'); if (el) el.textContent = fmtTL(matrah);
    el = document.getElementById('tot-kdv');    if (el) el.textContent = fmtTL(kdv);
    el = document.getElementById('tot-toplam'); if (el) el.textContent = fmtTL(toplam);
  }

  function fmtTL(v) {
    return v.toFixed(2).replace('.',',').replace(/\B(?=(\d{3})+(?!\d))/g,'.') + ' ₺';
  }

  function escAttr(s) { return String(s).replace(/"/g,'&quot;'); }

  // İlk satır
  var addBtn = document.getElementById('line-add');
  if (addBtn) {
    addBtn.addEventListener('click', function(){ addLine(); });
    if (lineEditor.children.length === 0) addLine();
  }
}

// ═══ Müşteri autocomplete (fatura/yeni.php) ═══════════════
var mukellefSearch = document.getElementById('mukellef-search');
if (mukellefSearch) {
  var dd = document.getElementById('mukellef-dd');
  var hidden = document.getElementById('mukellef-id');
  var timer;
  mukellefSearch.addEventListener('input', function(){
    clearTimeout(timer);
    var q = this.value.trim();
    if (q.length < 2) { dd.style.display = 'none'; return; }
    timer = setTimeout(function(){
      fetch('/api/mukellef-search.php?q='+encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(d){
          if (!d.results || !d.results.length) { dd.innerHTML = '<div style="padding:12px;color:#94a3b8;font-size:13px">Sonuç yok. <a href="/musteri/duzenle.php?new=1" target="_blank">Yeni müşteri ekle →</a></div>'; dd.style.display='block'; return; }
          dd.innerHTML = d.results.map(function(r){
            return '<a href="#" class="mk-item" data-id="'+r.id+'" data-unvan="'+escAttr(r.unvan)+'">'+
              '<strong>'+escHtml(r.unvan)+'</strong>'+
              '<small>'+escHtml(r.vkn_tip)+': '+escHtml(r.vkn_tckn)+(r.il?' · '+escHtml(r.il):'')+'</small>'+
            '</a>';
          }).join('');
          dd.style.display = 'block';
          dd.querySelectorAll('.mk-item').forEach(function(a){
            a.addEventListener('click', function(e){
              e.preventDefault();
              hidden.value = this.dataset.id;
              mukellefSearch.value = this.dataset.unvan;
              dd.style.display = 'none';
            });
          });
        });
    }, 250);
  });
  function escHtml(s){ return String(s).replace(/[&<>"']/g,function(m){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];}); }
  function escAttr(s){ return String(s).replace(/"/g,'&quot;'); }
  document.addEventListener('click', function(e){
    if (!mukellefSearch.contains(e.target) && !dd.contains(e.target)) dd.style.display='none';
  });
}

// ═══ Confirm silme ═══════════════════════════════════════
document.querySelectorAll('[data-confirm]').forEach(function(el){
  el.addEventListener('click', function(e){
    if (!confirm(this.dataset.confirm)) e.preventDefault();
  });
});

// ═══ Mobile sidebar toggle ═══════════════════════════════
var mobBtn = document.getElementById('mob-toggle');
if (mobBtn) mobBtn.addEventListener('click', function(){
  document.querySelector('.sidebar').classList.toggle('open');
});

})();
