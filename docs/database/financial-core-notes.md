# Financial Core Notes

`financial_transactions` currently represents the member financial-account subledger. Its posted entries are authoritative for balances on `financial_accounts`; pending entries do not affect balances, and posted entries are immutable.

The subledger is intentionally not a full organizational double-entry ledger yet. The UUID account identity, positive minor-unit amounts, direction, references, source payment, posting actor, and reversal relationship leave room for future organizational ledger accounts and journal-entry relationships needed by loan disbursement, repayment, recovery, and cash/bank movement. Those domains should extend the accounting model after review rather than changing member balance history or treating a mutable balance as authoritative.
