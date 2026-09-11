  <footer class="app-footer">
    <div style="max-width: 1100px; margin: 0 auto; display: flex; flex-direction: column; align-items: center; gap: 8px;">
      <p style="font-weight: 700; color: var(--text-main);">
        <i class="fa-solid fa-graduation-cap" style="color: var(--primary);"></i> Marwadi University Lost & Found System
      </p>
      <p style="font-size: 0.8rem; color: var(--text-muted);">
        UniFind Smart Platform • Official Campus Asset Recovery & Verification Portal
      </p>
      <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 4px;">
        &copy; <?= date('Y') ?> Marwadi University. All rights reserved.
      </p>
    </div>
  </footer>

  <!-- Flawless Vanilla JS Theme Toggle Script with localStorage Persistence -->
  <script>
    function toggleTheme() {
      const isDark = document.body.classList.contains('dark-theme') || 
                     document.documentElement.getAttribute('data-theme') === 'dark';
      
      const newTheme = isDark ? 'light' : 'dark';
      
      if (newTheme === 'dark') {
        document.body.classList.add('dark-theme');
        document.documentElement.classList.add('dark-theme');
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.body.classList.remove('dark-theme');
        document.documentElement.classList.remove('dark-theme');
        document.documentElement.setAttribute('data-theme', 'light');
      }
      
      localStorage.setItem('unifind_theme', newTheme);
      document.cookie = "theme=" + newTheme + "; path=/; max-age=31536000";
    }

    // Initialize Theme state on DOM load
    document.addEventListener('DOMContentLoaded', function() {
      const savedTheme = localStorage.getItem('unifind_theme') || 'light';
      if (savedTheme === 'dark') {
        document.body.classList.add('dark-theme');
        document.documentElement.classList.add('dark-theme');
        document.documentElement.setAttribute('data-theme', 'dark');
      } else {
        document.body.classList.remove('dark-theme');
        document.documentElement.classList.remove('dark-theme');
        document.documentElement.setAttribute('data-theme', 'light');
      }
    });
  </script>
</body>
</html>
