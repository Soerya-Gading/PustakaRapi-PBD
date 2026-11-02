<?php
declare(strict_types=1);

/**
 * PustakaRapi — Modern Layout Footer
 */
?>
        </div><!-- /.content-container -->
    </main><!-- /.app-main -->
</div><!-- /.app-layout -->

<!-- =========================================================================
     Global Confirmation Modal
     ========================================================================= -->
<div class="modal-backdrop" id="modal-confirm-delete" role="dialog" aria-modal="true" aria-labelledby="confirm-delete-title">
    <div class="modal-dialog" style="max-width: 440px;">
        <div class="modal-header">
            <h3 id="confirm-delete-title" style="display:flex; align-items:center; gap:8px; color:var(--danger);">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                Konfirmasi Hapus
            </h3>
            <button type="button" class="btn-ghost" data-close-modal style="min-height:auto; padding:4px;" aria-label="Tutup">&times;</button>
        </div>
        <div class="modal-body">
            <p id="confirm-delete-message" style="color:var(--text-main); font-size:0.95rem;">Apakah Anda yakin ingin menghapus data ini? Tindakan ini tidak dapat dibatalkan.</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close-modal>Batal</button>
            <button type="button" class="btn btn-danger" id="btn-confirm-delete-yes">Ya, Hapus Data</button>
        </div>
    </div>
</div>

<!-- =========================================================================
     Global Toast Container
     ========================================================================= -->
<div class="toast-container" id="toast-container" aria-live="polite"></div>

<script src="public/js/app.js"></script>
</body>
</html>

