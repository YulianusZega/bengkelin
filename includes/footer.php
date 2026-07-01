    </main><!-- end page-content -->
    
    <footer style="padding: 20px 24px; border-top: 1px solid var(--border); margin-top: auto; display: flex; justify-content: space-between; align-items: center; font-size: 13px; color: var(--text-muted);">
      <div>&copy; <?= date('Y') ?> Bengkelin — Teaching Factory</div>
      <div>Versi 2.1.0 Premium</div>
    </footer>
  </div><!-- end main-wrapper -->
</div><!-- end app-wrapper -->

<button id="scrollToTop" style="position: fixed; bottom: 24px; right: 24px; width: 40px; height: 40px; border-radius: 50%; background: var(--primary); color: #fff; border: none; cursor: pointer; display: none; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(255,107,43,.3); z-index: 99; transition: all .3s;">
  <i class="fas fa-arrow-up"></i>
</button>

<script>
  // Scroll to top functionality
  const sttBtn = document.getElementById('scrollToTop');
  const mainWrapper = document.querySelector('.main-wrapper');
  if (mainWrapper && sttBtn) {
    mainWrapper.addEventListener('scroll', () => {
      if (mainWrapper.scrollTop > 300) sttBtn.style.display = 'flex';
      else sttBtn.style.display = 'none';
    });
    sttBtn.addEventListener('click', () => {
      mainWrapper.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }
</script>

<script src="<?= BASE_URL ?>/assets/js/app.js?v=2.0"></script>
<?php if (!empty($extraJs)): foreach ($extraJs as $js): ?>
<script src="<?= BASE_URL ?>/assets/js/<?= $js ?>"></script>
<?php endforeach; endif; ?>
</body>
</html>
