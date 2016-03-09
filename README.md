# instructables-contest-stats
Crawls the instructable site, pulls info for contests and entries.


# Docker instructions:
Launch all:
`docker-compose up -d`

Connect to shell on container:
`docker  exec -it <containierid> bash`

Group by issue: ERROR 1055 (42000): Expression #5 of SELECT list is not in GROUP BY clause and contains nonaggregated column 'instructables.stats.views' which is not functionally dependent on columns in GROUP BY clause; this is incompatible with sql_mode=only_full_group_by

`SET sql_mode = ''`
