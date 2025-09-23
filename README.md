# classcardassignment

## Race conditions
-- If we enqueue jobs that mutate a user’s balance when invoices are created/updated/deleted .because we have multiple workers/servers jobs for the same user 
   can be processed in different orders (A then B vs B then A). If job B expects the action from job A to have already been applied, processing them out-of-order
   results in incorrect balances.
-- the root cause is lack of ordering gaurantee for jobs that are associated to logically same entity .

### to rsolve this issue we have two options :-
-- Radis queue partitions :- for each user we can have seprate dedicated partition there job belongs to a user processed sequentially with in that partion.
-- Kafka style keyed messages :- the idea is to use a brocker that gaurantees ordering by key , all message with same key processed in same partition and consumed in order.
    -- producer :- publish event with a key
    -- consumer :- run consumers in a consumer group Kafka ensures messages with same key always go to same partition and are processed in order by a single consumer thread for that partition.

