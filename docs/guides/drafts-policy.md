# Draft tickets policy

**Decision:** draft ticket saving was **removed**.

Tickets are submitted directly as `available` for pickup. There is no draft edit/submit workflow.

Rationale: the earlier optional `save_as_draft` path had no edit/submit UI, which risked orphaned drafts and incomplete duplicate checks. Prefer a single submit path with server-side validation and duplicate acknowledgement.
