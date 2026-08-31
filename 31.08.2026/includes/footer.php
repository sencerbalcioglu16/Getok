<?php
$footer_ligler = isset($pdo) ? $pdo->query("SELECT l.id,l.lig_adi FROM ligler l JOIN sezonlar s ON s.id=l.sezon_id WHERE l.aktif=1 AND s.durum='aktif' ORDER BY l.created_at")->fetchAll() : [];
$footer_turnuvalar = isset($pdo) ? $pdo->query("SELECT t.id,t.turnuva_adi FROM turnuvalar t JOIN sezonlar s ON s.id=t.sezon_id WHERE s.durum='aktif' ORDER BY t.created_at DESC")->fetchAll() : [];
?>
</div></div>
<footer class="footer">
    <div class="footer-inner">
        <div class="footer-brand">
            <img src="<?= BASE_URL ?>/assets/images/getok-logo.png" alt="Geleneksel Okçuluk Ligleri logosu" class="footer-logo">
            <div><strong>Geleneksel Türk Okçuluğu Bölge Ligleri</strong><small>Gelenekten gelen güç, hedefe giden yol.</small></div>
        </div>
        <div class="footer-column footer-lig-menu">
            <strong>Ligler ve Turnuvalar</strong><span class="footer-menu-heading">Ligler</span>
            <?php foreach ($footer_ligler as $fl): ?><a href="<?= BASE_URL ?>/lig.php?id=<?= (int)$fl['id'] ?>"><?= e($fl['lig_adi']) ?></a><?php endforeach; ?>
            <a href="<?= BASE_URL ?>/ligler.php">Tüm Ligler</a><span class="footer-menu-heading">Turnuvalar</span>
            <?php foreach ($footer_turnuvalar as $ft): ?><a href="<?= BASE_URL ?>/turnuva.php?id=<?= (int)$ft['id'] ?>">🏆 <?= e($ft['turnuva_adi']) ?></a><?php endforeach; ?>
            <a href="<?= BASE_URL ?>/turnuvalar.php">Tüm Turnuvalar</a><a href="<?= BASE_URL ?>/arsiv.php">Arşiv</a>
        </div>
        <div class="footer-column">
            <strong>Kurumsal</strong><a href="<?= BASE_URL ?>/hakkimizda.php">Hakkımızda</a><a href="<?= BASE_URL ?>/yonetmelikler.php">Yönetmelikler</a><a href="<?= BASE_URL ?>/iletisim.php">İletişim</a><a href="<?= BASE_URL ?>/destekleyenler.php">Destekleyenler</a>
        </div>
    </div>
    <div class="footer-copy">© <?= date('Y') ?> Geleneksel Türk Okçuluğu Bölge Ligleri · Geliştirici: Sencer BALCIOĞLU · <a href="<?= BASE_URL ?>/LICENSE" target="_blank" rel="license noopener">GNU GPL v3.0 ile lisanslanmıştır</a></div>
</footer>
<script>
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('table.data-table').forEach(function(table){
    var head=table.tHead;if(!head||!head.rows.length||!table.tBodies.length)return;
    Array.prototype.forEach.call(head.rows[0].cells,function(th,index){
      if(th.querySelector('.table-sort-controls'))return;
      var controls=document.createElement('span');controls.className='table-sort-controls';
      [['↑','Artan sıralama','asc'],['↓','Azalan sıralama','desc']].forEach(function(item){
        var button=document.createElement('button');button.type='button';button.className='table-sort-button';button.textContent=item[0];button.title=item[1];button.setAttribute('aria-label',item[1]+': '+th.textContent.trim());
        button.addEventListener('click',function(){
          var rows=Array.prototype.slice.call(table.tBodies[0].rows),direction=item[2]==='asc'?1:-1;
          var deger=function(row){return (row.cells[index]?row.cells[index].textContent:'').trim();};
          rows.sort(function(a,b){var av=deger(a),bv=deger(b),an=Number(av.replace(/\./g,'').replace(',','.')),bn=Number(bv.replace(/\./g,'').replace(',','.'));if(av!==''&&bv!==''&&!isNaN(an)&&!isNaN(bn))return (an-bn)*direction;return av.localeCompare(bv,'tr',{numeric:true,sensitivity:'base'})*direction;});
          rows.forEach(function(row,i){table.tBodies[0].appendChild(row);if(index!==0&&head.rows[0].cells[0].textContent.trim().charAt(0)==='#'&&row.cells[0])row.cells[0].textContent=i+1;});
          table.querySelectorAll('.table-sort-button').forEach(function(x){x.classList.remove('active')});button.classList.add('active');
        });controls.appendChild(button);
      });th.appendChild(controls);
    });
  });
});
</script>
</body></html>
