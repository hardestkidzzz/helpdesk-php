</div> <!-- .container -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  const root = document.documentElement; // <html>
  const key = 'theme-pref';
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  const initial = localStorage.getItem(key) || (prefersDark ? 'dark' : 'light');

  function setTheme(mode){
    root.setAttribute('data-bs-theme', mode);
    localStorage.setItem(key, mode);
    const btn = document.getElementById('themeToggle');
    if (btn) btn.innerHTML = (mode === 'dark')
      ? '<i class="bi bi-sun"></i>'
      : '<i class="bi bi-moon"></i>';
  }
  setTheme(initial);

  const btn = document.getElementById('themeToggle');
  if (btn) btn.addEventListener('click', () => {
    const next = root.getAttribute('data-bs-theme') === 'light' ? 'dark' : 'light';
    setTheme(next);
  });
})();
</script>
</body>
</html>
