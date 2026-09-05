jQuery(document).ready(function($) {
    let isRunning = false;
    let isScanning = false;

    function formatBytes(bytes, decimals = 2) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
    }

    function updateDashboard(data) {
        if (!data) return;
        $('#stat-total').text(data.total || 0);
        $('#stat-converted').text(data.converted || 0);
        $('#stat-pending').text(data.pending || 0);
        $('#stat-failed').text(data.failed || 0);
        $('#saved-space').text(formatBytes(data.saved_bytes || 0));
        
        let progress = data.progress || 0;
        $('#main-progress-bar').css('width', progress + '%');
        $('#main-progress-text').text(progress + '%');
        
        if (progress >= 100) {
            $('#main-progress-bar').addClass('completed');
            $('#main-progress-text').addClass('completed');
        } else {
            $('#main-progress-bar').removeClass('completed');
            $('#main-progress-text').removeClass('completed');
        }
    }

    function fetchStats() {
        $.post(onewebp_vars.ajaxurl, { action: 'onewebp_get_stats', security: onewebp_vars.nonce }, function(res) {
            if (res.success) updateDashboard(res.data);
        });
    }

    function scanLibrary(offset = 0) {
        if (isScanning) return;
        isScanning = true;
        
        $('#start-optimize-btn').prop('disabled', true).text('Scanning...').css('opacity', '0.6');
        $('#rescan-btn').prop('disabled', true);

        $.post(onewebp_vars.ajaxurl, { action: 'onewebp_scan_library', security: onewebp_vars.nonce, offset: offset }, function(res) {
            if (res.success) {
                if (res.data.done) {
                    isScanning = false;
                    fetchStats(); // Update UI after scan
                    $('#start-optimize-btn').prop('disabled', false).text('Start Optimization').css('opacity', '1');
                    $('#rescan-btn').prop('disabled', false);
                } else {
                    scanLibrary(res.data.next_offset);
                }
            } else {
                isScanning = false;
                $('#start-optimize-btn').prop('disabled', false).text('Start Optimization').css('opacity', '1');
                $('#rescan-btn').prop('disabled', false);
            }
        });
    }

    // Start scanning immediately
    scanLibrary(0);

    $('#rescan-btn').on('click', function() {
        if(isRunning) return;
        scanLibrary(0);
    });

    $('#start-optimize-btn').on('click', function() {
        if (isScanning || $(this).prop('disabled')) return;
        isRunning = true; 
        $(this).hide(); 
        $('#stop-optimize-btn').show(); 
        runBatch();
    });

    $('#stop-optimize-btn').on('click', function() {
        isRunning = false; 
        $(this).hide(); 
        $('#start-optimize-btn').show(); 
    });

    function runBatch() {
        if (!isRunning) return;
        $.post(onewebp_vars.ajaxurl, { action: 'onewebp_run_batch', security: onewebp_vars.nonce }, function(res) {
            if (res.success) {
                fetchStats(); // Refresh stats during processing
                if (res.data.done) {
                    isRunning = false; 
                    $('#start-optimize-btn').show(); 
                    $('#stop-optimize-btn').hide();
                } else { 
                    setTimeout(runBatch, 300); 
                }
            }
        });
    }
});