<?php
$role = getUserRole();
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
    #sidebar {
        background: linear-gradient(180deg, var(--accent-primary) 0%, var(--accent-secondary) 100%);
    }
    #sidebar.collapsed {
        width: 64px !important;
        min-width: 64px !important;
        max-width: 64px !important;
        align-items: center;
    }
    #sidebar.expanded {
        width: 256px !important;
        min-width: 256px !important;
        max-width: 256px !important;
    }
    #sidebar .sidebar-text {
        transition: opacity 0.2s, width 0.2s;
        white-space: nowrap;
    }
    #sidebar.collapsed .sidebar-text {
        opacity: 0;
        width: 0;
        overflow: hidden;
    }
    #sidebar .sidebar-icon {
        min-width: 24px;
        text-align: center;
        margin-right: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    #sidebar .sidebar-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 8px;
        margin-top: 0;
        transition: padding 0.2s;
    }
    #sidebar.collapsed .sidebar-link {
        justify-content: center;
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    #sidebar .sidebar-logo {
        width: 44px;
        height: 44px;
        object-fit: contain;
        border-radius: 8px;
        margin: 18px auto 8px auto;
        display: block;
        transition: width 0.2s, height 0.2s, margin 0.2s;
    }
    #sidebar.collapsed .sidebar-logo {
        width: 36px;
        height: 36px;
        margin: 12px auto 4px auto;
    }
    #sidebar .sidebar-toggle-row {
        width: 100%;
        display: flex;
        align-items: center;
        margin-bottom: 10px;
        margin-top: 0;
        transition: justify-content 0.2s, padding 0.2s;
    }
    #sidebar.expanded .sidebar-toggle-row {
        justify-content: flex-start;
        padding-left: 18px;
    }
    #sidebar.collapsed .sidebar-toggle-row {
        justify-content: center;
        padding-left: 0;
    }

    /* Dark mode specific sidebar adjustments */
    .dark #sidebar {
        background: linear-gradient(180deg, #dc2626 0%, #991b1b 100%);
    }

    .dark #sidebar .sidebar-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }

    .dark #sidebar .sidebar-link.active {
        background-color: rgba(255, 255, 255, 0.2);
    }
</style>

<div id="sidebar" class="flex flex-col fixed inset-y-0 left-0 text-white expanded transform transition-all duration-300 ease-in-out z-30">
    <img src="assets/logo.jpg" alt="Logo" class="sidebar-logo" />
    <div class="sidebar-toggle-row">
        <button id="sidebarToggle" class="text-red-100 hover:text-white focus:outline-none">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <span class="text-xl font-semibold sidebar-text transition-opacity duration-300 ml-3" id="sidebarTitle">Task Manager</span>
    </div>
    <nav class="flex-1 flex flex-col justify-center mt-2 px-2">
        <a href="dashboard.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'dashboard.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="Dashboard">
            <span class="sidebar-icon">
                <i class="fas fa-home fa-lg"></i>
            </span>
            <span class="sidebar-text ml-4" id="dashboardText">Dashboard</span>
        </a>
        <?php if ($role === 'employee'): ?>
            <a href="mark_attendance.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'mark_attendance.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="Mark Attendance">
                <span class="sidebar-icon"><i class="fas fa-calendar-check fa-lg"></i></span>
                <span class="sidebar-text ml-4" id="markAttendanceText">Mark Attendance</span>
            </a>
            <a href="my_tasks.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'my_tasks.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="My Tasks">
                <span class="sidebar-icon"><i class="fas fa-tasks fa-lg"></i></span>
                <span class="sidebar-text ml-4" id="myTasksText">My Tasks</span>
            </a>
        <?php endif; ?>
        <?php if ($role === 'admin' || $role === 'manager'): ?>
            <a href="attendance.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'attendance.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="Attendance">
                <span class="sidebar-icon"><i class="fas fa-user-check fa-lg"></i></span>
                <span class="sidebar-text ml-4" id="attendanceText">Attendance</span>
            </a>
            <a href="tasks.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'tasks.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="Tasks">
                <span class="sidebar-icon"><i class="fas fa-list fa-lg"></i></span>
                <span class="sidebar-text ml-4" id="tasksText">Tasks</span>
            </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
            <a href="users.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md <?php echo $currentPage === 'users.php' ? 'bg-red-800 text-white' : 'text-red-100 hover:bg-red-700 hover:text-white'; ?>" title="Users">
                <span class="sidebar-icon"><i class="fas fa-users-cog fa-lg"></i></span>
                <span class="sidebar-text ml-4" id="usersText">Users</span>
            </a>
        <?php endif; ?>
        <a href="logout.php" class="sidebar-link group px-2 py-2 text-base leading-6 font-medium rounded-md text-red-100 hover:bg-red-700 hover:text-white" title="Logout">
            <span class="sidebar-icon"><i class="fas fa-sign-out-alt fa-lg"></i></span>
            <span class="sidebar-text ml-4" id="logoutText">Logout</span>
        </a>
    </nav>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mainContent = document.getElementById('mainContent');
    const topNav = document.getElementById('topNav');
    
    let isCollapsed = false;
    
    // Check if sidebar state is saved
    const savedState = localStorage.getItem('sidebarCollapsed');
    if (savedState === 'true') {
        toggleSidebar();
    }
    
    sidebarToggle.addEventListener('click', function() {
        toggleSidebar();
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    });
    
    function toggleSidebar() {
        isCollapsed = !isCollapsed;
        
        if (isCollapsed) {
            sidebar.classList.remove('expanded');
            sidebar.classList.add('collapsed');
            mainContent.style.marginLeft = '64px';
            topNav.style.marginLeft = '64px';
        } else {
            sidebar.classList.remove('collapsed');
            sidebar.classList.add('expanded');
            mainContent.style.marginLeft = '256px';
            topNav.style.marginLeft = '256px';
        }
    }
});
</script> 