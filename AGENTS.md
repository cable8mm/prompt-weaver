# AGENTS.md

## DOCUMENT_ORDER

1. AGENTS.md
2. .replworks/PRODUCT_SPEC.md
3. .replworks/TECH_STACK.md
4. .replworks/ARCHITECTURE.md
5. .replworks/TASKS.md

Only these documents are authoritative.

---

## IGNORE

Ignore all files under:

```text
.replworks/docs/
```

Never use files in these folders as requirements.
Never implement features described only in these folders.

---

## REQUEST_LANES

Classify every human request before acting.
State the lane in the first line of the response.

Lanes reduce reading and ceremony. They never relax constraints:
SOURCE_OF_TRUTH, MOCK_RULES, and EXTERNAL_BOUNDARY rules apply in every lane.

### QUESTION

The human asks to explain, review, compare, or investigate.

- Answer directly. Read only what is needed to answer.
- Do not edit files, run tests, select a task, or touch documents.
- If a conflict or gap is found, mention it in one line.
- Stop.

### SMALL_CHANGE

The human asks for a specific, bounded code change that is not a TASKS.md task
(bug fix, small refactor, small addition within already-specified behavior).

- Inspect only the code and tests directly involved.
- Read project documents only if the change touches behavior,
  architecture, or stack rules that cannot be determined from the code.
- Make only the requested change.
- Run the tests directly related to the change,
  plus verification commands required by TECH_STACK.md.
- Do not edit TASKS.md or any other document.
- Report in a few lines. Stop.

Escalate to TASK: stop and tell the human if the change:

```text
adds or alters behavior defined in PRODUCT_SPEC.md
touches an EXTERNAL_BOUNDARY
requires changing TECH_STACK.md or ARCHITECTURE.md
touches an [UNVERIFIED] section
spans many files or modules
```

### TASK

The human names a task, or says to proceed with the next task.

- Follow TASK_SELECTION, TASK_CONTEXT, and TASK_EXECUTION in full.

### UNCLEAR

If the lane is unclear, choose the lighter lane.
Escalate only when an escalation condition is met.

---

## SOURCE_OF_TRUTH

Product Requirements:

```text
PRODUCT_SPEC.md
```

Implementation Constraints:

```text
TECH_STACK.md
```

Architecture:

```text
ARCHITECTURE.md
```

Execution Plan:

```text
TASKS.md
```

If a conflict exists:

```text
PRODUCT_SPEC.md
>
TECH_STACK.md
>
ARCHITECTURE.md
>
TASKS.md
>
everything else
```

---

## CONFLICT_HANDLING

When documents conflict:

1. Report the conflict to the human.
2. Continue only the parts of the work that do not depend on the conflict.
   For the dependent parts, follow the higher-priority document
   and do not finalize until the human confirms.
3. Fix the lower-priority document with human confirmation before completing the task.

Never resolve a conflict silently.
Never mark a task `[X]` while a conflict affecting it is unresolved.

---

## DOCUMENT_RESPONSIBILITIES

PRODUCT_SPEC.md defines:

```text
What the product is.
What the product does.
```

TECH_STACK.md defines:

```text
How the product must be implemented.
```

ARCHITECTURE.md defines:

```text
How the product works.
```

TASKS.md defines:

```text
What should be implemented next.
```

Do not move responsibilities between documents.

ARCHITECTURE.md must not contain technology, versions, or folder rules.
TECH_STACK.md must not contain component responsibilities or data flows.

---

## EXTERNAL_BOUNDARY

External boundary = any behavior not controlled by this codebase.

Examples:

```text
third-party DOM
third-party API
browser runtime behavior
```

Libraries running inside this codebase's own process are not an external boundary.

---

## UNVERIFIED_MARK

Add `[UNVERIFIED]` to the heading of the section that owns the gap.

```text
Gaps in what the product is or does         -> PRODUCT_SPEC.md
Gaps in how the product must be implemented -> TECH_STACK.md
Gaps in how the product works               -> ARCHITECTURE.md
```

Mark a gap when the selected task touches a domain
not covered by verified knowledge in the documents.
Then stop and require explicit human confirmation.

Do not remove `[UNVERIFIED]` without human confirmation.

An `[UNVERIFIED]` section unrelated to the selected task does not block the task.
A selected task must not be implemented against an `[UNVERIFIED]` section.

---

## IMPLEMENTATION_RULES

Implement only what the lane allows:
the selected task (TASK) or the requested change (SMALL_CHANGE).

Do not implement:

```text
future work
roadmap items
optional features
assumptions
inferred requirements
unrequested improvements
```

Requirements must originate from:

```text
PRODUCT_SPEC.md
```

Implementation must follow:

```text
TECH_STACK.md
ARCHITECTURE.md
```

Do not create additional behavior to make an implementation appear complete.

---

## TASK_SELECTION

Applies only in the TASK lane.

Implement the task named by the human.
If the human says to proceed without naming a task,
use the first unchecked task in TASKS.md, from top to bottom.
If the human did not ask to implement anything, do not select a task.

Do not select a different task because it appears easier,
more useful, or already partially implemented.

---

## TASK_CONTEXT

Do not read all project documents for every task by default.

At the start of a task:

1. Read the selected task in TASKS.md.
2. Search PRODUCT_SPEC.md, TECH_STACK.md, and ARCHITECTURE.md for `[UNVERIFIED]`.
3. Read the PRODUCT_SPEC.md sections referenced by the task.
4. Read the TECH_STACK.md sections relevant to the task.
5. Read the ARCHITECTURE.md sections relevant to the task.
6. Inspect only the source code and tests needed to implement and verify the task.

Read additional document sections only when:

```text
a referenced section depends on them
a conflict must be resolved
a dependency is required to implement the task
the task's references are missing or ambiguous
```

Do not inspect unrelated requirements, architecture, technologies, or future work.

---

## TASK_EXECUTION

For every task:

1. Select the task.
2. Read the task definition and its referenced document sections.
3. Check whether any required section is `[UNVERIFIED]`.
4. Check whether the task touches a domain not covered by verified
   knowledge. If so, mark the gap (see UNVERIFIED_MARK) and stop.
5. Check for conflicts affecting the task.
6. Inspect the relevant source code and tests.
7. Implement only the selected task.
8. Write or update unit tests for applicable internal logic.
9. If the task touches an EXTERNAL_BOUNDARY:
   - perform the required live observation
   - run an E2E test against the live boundary
   - do not treat a mocked test as a substitute
   - if the live boundary is unreachable, stop and report
10. Run the tests relevant to the changed behavior.
11. Run every verification command defined in TECH_STACK.md.
12. Verify every acceptance criterion of the selected task.
13. Mark the completed task `[X]` in TASKS.md.
14. Stop.

Do not automatically start another task.

---

## EXTERNAL_BOUNDARY_TASKS

For an external-boundary task:

- The task must identify the relevant external boundary.
- Live behavior must be observed before relying on it.
- Mocked behavior must be based only on an observed response or documented spec.
- Implementation tasks require a live E2E test.
- Verification tasks require a live probe or equivalent executable check.
- Failure behavior defined by ARCHITECTURE.md must be verified where applicable.

A live E2E test may be reused as evidence only when the task's required
external behavior is unchanged and the existing test still exercises that behavior.

If the external behavior has changed or the evidence is stale, re-verify it.

---

## MOCK_RULES

Mock only observed behavior.

Allowed sources:

```text
recorded live response
documented specification
```

Forbidden sources:

```text
assumed behavior
guessed response
inferred event flow
```

If a mock's values cannot be traced to a recorded observation or a spec,
do not write it.

Any code touching an EXTERNAL_BOUNDARY requires at least one valid
observation before that behavior may be mocked.

Mocks do not replace required live E2E verification.

---

## DOCUMENT_CHANGES

If implementation reveals a missing or incorrect requirement,
architecture rule, or tech-stack constraint:

1. Stop.
2. Report the issue to the human.
3. Propose the required document change.
4. Update the document only after human confirmation.
5. Continue only after the document and implementation are consistent.

Apply this to:

```text
PRODUCT_SPEC.md
TECH_STACK.md
ARCHITECTURE.md
```

Never change a higher-priority document to make the implementation fit.
Never allow documents and code to diverge.

---

## TASK_CHANGES

If implementation invalidates the selected task,
or a document change makes the task incorrect:

1. Stop.
2. Propose the TASKS.md change to the human.
3. Update TASKS.md only after human confirmation.

Never change an existing task ID.
Never regenerate TASKS.md from scratch.

The human may edit TASKS.md directly at any time.
Human edits are authoritative.

---

## HUMAN_OWNED_CHANGES

Content and visual design are human-owned.
They are not specified in PRODUCT_SPEC.md and are not tracked in TASKS.md.

```text
Content:       wording, copy, translations, images, data text
Visual design: styling, spacing, colors, typography, imagery, visual polish
```

Not human-owned:

```text
screens
elements
element purposes
arrangement
states
interactions
observable behavior
```

These are specified in PRODUCT_SPEC.md.

When the human explicitly asks for a human-owned change:

1. Make only that change.
2. Do not change behavior, screens, elements, states, or interactions
   defined in PRODUCT_SPEC.md.
3. If the change requires a product-behavior change, stop
   and follow DOCUMENT_CHANGES.
4. Do not record the change in TASKS.md or any other document.
5. Run the verification commands defined in TECH_STACK.md.
6. Stop.

Never make human-owned changes on your own initiative.

If a task requires content or styling and none is provided,
use neutral placeholders and report them when you stop.

---

## DESIGN_RULES

Prefer:

```text
simple
explicit
minimal
```

Avoid:

```text
abstraction without use
premature optimization
speculative features
```

---

## FILE_CREATION

Do not create new top-level documents unless explicitly requested.
Prefer modifying existing files.

---

## SUCCESS_CRITERIA

QUESTION: the question is answered. Nothing else.

SMALL_CHANGE is complete when:

```text
the requested change is made and nothing more
directly related tests pass
required TECH_STACK.md verification commands pass
no escalation condition was met
```

TASK is complete only when:

```text
product requirements for the task are satisfied
architectural requirements relevant to the task are satisfied
tech-stack constraints relevant to the task are satisfied
all task acceptance criteria are satisfied
no [UNVERIFIED] section is required by the task
no unresolved conflict affects the task
relevant tests pass
required TECH_STACK.md verification commands pass
live E2E passes for required external-boundary tasks
```

Then stop.
