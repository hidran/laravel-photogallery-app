Check the current diff against DESIGN.md for specification drift.

1. Run `git diff` (or `git diff --cached` if staged) to see changes
2. Identify which DESIGN.md sections the changes relate to (§4 for models, §6 for API, §7 for Filament, §8 for frontend, etc.)
3. Read those sections from `docs/DESIGN.md`
4. Compare the implementation against the spec, checking:
   - Column types and nullability match §4
   - Response shapes match §6.6
   - Validation rules match §6.2/§6.3
   - File paths match §3.2
   - Status codes match §6.7
   - Eager loads match documented constants
5. Report any drift as a list with file:line references
6. If no drift found, confirm the changes align with the spec
