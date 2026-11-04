</main>
<footer class="bf-footer mt-5">
  <div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
      <div><div class="footer-brand">✦ BudgetFairy</div><p class="small text-muted mb-0">Make smarter money moves, before you make them.</p></div>
      <div class="small text-muted align-self-md-end">A financial simulator · © <?= date('Y') ?> BudgetFairy</div>
    </div>
    <p class="disclaimer small mt-3 mb-0">BudgetFairy provides educational estimates, and financial advice. Figures depend on the information entered by the user.</p>
  </div>
</footer>
<div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastWrap" aria-live="polite" aria-atomic="true"></div>
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content modal-soft">
  <div class="modal-body text-center p-4"><div class="confirm-icon">?</div><p class="mb-3 fw-semibold" id="confirmText">Are you sure?</p>
  <div class="d-flex gap-2 justify-content-center"><button type="button" class="btn btn-outline-secondary btn-sm px-3" id="confirmNo">Cancel</button><button type="button" class="btn btn-danger btn-sm px-3" id="confirmYes">Yes, delete</button></div></div>
</div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js"></script>
</body>
</html>
