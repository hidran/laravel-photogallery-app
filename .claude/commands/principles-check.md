Evaluate the current diff against CLAUDE.md "Engineering principles" and "Critical rules".

1. Run `git diff` (or `git diff --cached` if staged) to see changes
2. Read `CLAUDE.md` sections: "Critical rules", "Engineering principles" (SOLID, REST, KISS, DRY)
3. Check each changed file against these principles:
   - **Single Responsibility:** Does each class have one reason to change?
   - **Open/Closed:** Are new behaviors added via extension, not modification?
   - **Liskov:** Do subtypes honor their contracts?
   - **Interface Segregation:** Are interfaces focused?
   - **Dependency Inversion:** Dependencies on abstractions, not concretes?
   - **REST:** Resources not actions? Correct status codes? Uniform envelope?
   - **KISS:** Simplest implementation that meets criteria?
   - **DRY:** No duplicated logic? But no premature abstraction?
4. Check critical rules:
   - `{ "data": ... }` envelope on all endpoints?
   - UUID v7 on all models?
   - Two photo disks used correctly?
   - Authorization + token abilities checked?
   - `DB::transaction` on multi-write ops?
   - Eager loads declared?
   - No hardcoded strings in JSX?
5. Report violations with file:line citations
6. Suggest fixes for each violation
