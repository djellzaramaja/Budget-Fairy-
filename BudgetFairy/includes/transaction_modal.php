<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content modal-soft">
  <div class="modal-header"><h5 class="modal-title" data-form-title data-add-title="Add a transaction" data-edit-title="Edit transaction">Add a transaction</h5><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
  <div class="modal-body">
    <form id="transactionForm" class="row g-3" data-api="../api/transactions.php" data-reload>
      <input type="hidden" name="id" value="">
      <div class="col-6"><label class="form-label">Type</label><select class="form-select" name="type" required><option value="expense">Expense</option><option value="income">Income</option></select></div>
      <div class="col-6"><label class="form-label">Amount (€)</label><input class="form-control" name="amount" type="number" min="0.01" step="0.01" required></div>
      <div class="col-12"><label class="form-label">Description</label><input class="form-control" name="description" maxlength="120" required placeholder="e.g. Part-time job"></div>
      <div class="col-6"><label class="form-label">Category</label><input class="form-control" name="category" maxlength="60" required placeholder="e.g. Food"></div>
      <div class="col-6"><label class="form-label">Date</label><input class="form-control" name="transaction_date" type="date" value="<?= date('Y-m-d') ?>" required></div>
      <div class="col-12"><button class="btn btn-primary w-100" type="submit" data-submit-label data-add-label="Save transaction" data-edit-label="Update transaction">Save transaction</button><div class="form-message small mt-2" aria-live="polite"></div></div>
    </form>
  </div>
</div></div></div>
