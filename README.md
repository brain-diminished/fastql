FastQL
=====

This library offers a way to lighten the writing of SQL queries, by automatically adding all joins that can be deduced
from the schema. This allows to use object-like expressions, where object properties are based on foreign key name
(fk_*local_table*__*property_name*), foreign key column name (*property_name*_id or id_*property_name*), or referenced
table as a fallback (singularized using Doctrine's Inflector).

Examples
--------
```sql
-- example 1: Select all users
SELECT `users`.*;
-- example 2: Select users living in France
SELECT `users`.*
WHERE `users`.`address`.`country`.`code`="FR";
-- example 3: Select countries where at least one user resides
SELECT DISTINCT `users`.`address`.`country`.*;
-- example 4: Select goods that have been bought by a user residing in Spain
SELECT `goods`.*
WHERE `goods`.`\good`.`transactions`.`buyer`.`address`.`country`.`code` = "ES"
-- example 5: Select countries code and the list of countries they export to
SELECT `countries`.`code` `exporter`, GROUP_CONCAT(DISTINCT `countries`.`\?country`.`\address`.`\seller`.`buyer`.`address`.`country`.`code` SEPARATOR ";") `importer`
GROUP BY `countries`.`id`
ORDER BY `countries`.`code` ASC
```

Explanation
-----------
- `.` is a direct access, meaning it is solved without ambiguity, using the local foreign key corresponding to the
property accessed.

- `\` is an indirect access, meaning it is solved with possible ambiguities, using remote foreign keys referencing current
type (ie table) with property required. You may solve ambiguities, if any, by following directly the `\` property by a
`.` referencing targeted table (cf `example 4`)
