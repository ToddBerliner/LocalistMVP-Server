SELECT
u.first_name, DATE_FORMAT(la.created, '%Y-%m-%d'), COUNT(*)
FROM
localist_alerts la
JOIN
users u on la.meta = u.id
WHERE
GROUP BY 1, 2;
la.action = 300;
