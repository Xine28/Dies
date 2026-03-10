$(function () {
    var $modal = $(
        '<div id="logoutConfirmModal" style="display:none;position:fixed;inset:0;background:rgba(8,20,31,.45);z-index:99999;align-items:center;justify-content:center;padding:14px;">' +
            '<div style="width:min(420px,96%);background:#fff;border:1px solid #dbe7f5;border-radius:14px;box-shadow:0 18px 46px rgba(8,20,31,.22);padding:16px;">' +
                '<div style="font-weight:800;font-size:1rem;color:#0f172a;margin-bottom:6px;">Logout Confirmation</div>' +
                '<div style="font-size:.92rem;color:#475569;margin-bottom:14px;">Are you sure you want to logout?</div>' +
                '<div style="display:flex;justify-content:flex-end;gap:8px;">' +
                    '<button type="button" id="logoutCancelBtn" style="padding:8px 12px;border:none;border-radius:9px;background:#e2e8f0;color:#1e293b;font-weight:700;cursor:pointer;">Cancel</button>' +
                    '<button type="button" id="logoutOkBtn" style="padding:8px 12px;border:none;border-radius:9px;background:#dc2626;color:#fff;font-weight:700;cursor:pointer;">Logout</button>' +
                '</div>' +
            '</div>' +
        '</div>'
    );

    $('body').append($modal);

    var pendingHref = null;

    $(document).on('click', 'a[href$="logout.php"]', function (e) {
        e.preventDefault();
        pendingHref = $(this).attr('href');
        $modal.css('display', 'flex');
    });

    $(document).on('click', '#logoutCancelBtn', function () {
        pendingHref = null;
        $modal.hide();
    });

    $(document).on('click', '#logoutOkBtn', function () {
        if (pendingHref) {
            window.location.href = pendingHref;
        } else {
            $modal.hide();
        }
    });

    $modal.on('click', function (e) {
        if (e.target === this) {
            pendingHref = null;
            $modal.hide();
        }
    });
});
