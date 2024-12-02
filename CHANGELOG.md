## 22.05.2024 version 2.0.0  
- Refactor the code  
- Adjust the mechanism to accumulate learning progress. Only retrieve users with status=2(passed)
- Remove the grouping
- Versioning and add change log

## 27.05.2024 version 2.0.1  
- Remove verbose debugging from production code

## 25.06.2024 version 2.0.2
- Fix the aggregation code(with CTEs)
- Skip empty statuses

## 02.12.2024 version 2.0.3
- Add Jobs to find and Fix learning progress inconsistencies(non-matching dates and status)
- Add a logging mechanism
- Add a job to clean certificates(deactivate wrongly assigned certiticates)
- Refactor: Remove ilLFStatusLP -->  move logic to the cron job class

## 02.12.2024 version 3.0
- Support for ILIAS 9
- Replace the logs module with SummaryLogger
- Many PHP Fixes
