/**
 * main.js - Global UI utilities and interactivity for StemFlow AI
 */

window.StemFlow = {
    toast(message, type = 'info') {
        const container = document.getElementById('toastContainer');
        if (!container) {
            const div = document.createElement('div');
            div.id = 'toastContainer';
            div.style.cssText = 'position: fixed; bottom: 20px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 10px;';
            document.body.appendChild(div);
        }

        const toast = document.createElement('div');
        const bgColors = {
            info: '#2563EB',
            success: '#10B981',
            warning: '#F59E0B',
            error: '#EF4444'
        };

        toast.style.cssText = `
            background-color: ${bgColors[type] || '#1F2937'};
            color: #fff;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.4);
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: fadeIn 0.2s ease;
        `;

        const icon = type === 'success' ? 'fa-check-circle' : (type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle');
        toast.innerHTML = `<i class="fas ${icon}"></i> <span>${message}</span>`;
        document.getElementById('toastContainer').appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transition = 'opacity 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 3500);
    },

    async confirmDeleteProject(projectId, projectTitle, onDeleted) {
        if (!confirm(`Are you sure you want to permanently delete "${projectTitle}"?\nAll extracted stems, files, and settings will be removed.`)) {
            return;
        }

        try {
            const res = await fetch('api/projects/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: projectId })
            });
            const data = await res.json();
            if (data.success) {
                StemFlow.toast('Project deleted successfully', 'success');
                if (onDeleted) onDeleted();
            } else {
                StemFlow.toast(data.error || 'Failed to delete project', 'error');
            }
        } catch (err) {
            StemFlow.toast('Network error while deleting project', 'error');
        }
    },

    async renameProject(projectId, currentTitle, onRenamed) {
        const newTitle = prompt('Enter new project title:', currentTitle);
        if (!newTitle || newTitle.trim() === '' || newTitle.trim() === currentTitle) {
            return;
        }

        try {
            const res = await fetch('api/projects/rename.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ project_id: projectId, title: newTitle.trim() })
            });
            const data = await res.json();
            if (data.success) {
                StemFlow.toast('Project renamed', 'success');
                if (onRenamed) onRenamed(data.title);
            } else {
                StemFlow.toast(data.error || 'Failed to rename project', 'error');
            }
        } catch (err) {
            StemFlow.toast('Network error while renaming project', 'error');
        }
    }
};
