    </main>
    <footer class="bg-white shadow-lg mt-8">
        <div class="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> Work & Attendance Management System. All rights reserved.
            </p>
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mainContent = document.getElementById('mainContent');
        const topNav = document.getElementById('topNav');
        
        // Function to update layout based on sidebar state
        function updateLayout(isCollapsed) {
            if (isCollapsed) {
                sidebar.classList.remove('expanded');
                sidebar.classList.add('collapsed');
                mainContent.classList.remove('ml-64');
                mainContent.classList.add('ml-16');
                topNav.classList.remove('ml-64');
                topNav.classList.add('ml-16');
            } else {
                sidebar.classList.remove('collapsed');
                sidebar.classList.add('expanded');
                mainContent.classList.remove('ml-16');
                mainContent.classList.add('ml-64');
                topNav.classList.remove('ml-16');
                topNav.classList.add('ml-64');
            }
        }
        
        // Check if sidebar state is stored
        const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        updateLayout(sidebarCollapsed);
        
        // Toggle sidebar
        sidebarToggle.addEventListener('click', function() {
            const isCollapsed = sidebar.classList.contains('collapsed');
            updateLayout(!isCollapsed);
            localStorage.setItem('sidebarCollapsed', !isCollapsed);
        });
    });
    </script>
</body>
</html> 