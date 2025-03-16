# Pulse: Lightweight & Easy to Use Database Abstraction Layer
Providing a standard and clean format across the many flavors of SQL supported by PHP

I began working on Pulse while, during a project, I was maintaining three distinct SQL databases. I needed the syntax to
be standardised in the code so that -- as I migrated the data from one database engine to another -- I would not have to
modify the business logic. I had looked into PDO but found I couldn't get along with the syntax, hence creating my own
solution.

Pulse works by having a main "Command" class which is abstracted into different "Drivers" (though under the hood they
just use the drivers you're already familiar with). This means that all the commands you send to the database use an 
easy to remember, clean and safe-to-use syntax.

Pulse does not allow for raw queries, every query you write is first prepared and then sent to the database. This means
you will not be able to write multi-queries. There are a few reasons for this, however I would strongly advise you become
familiar with stored procedures and functions if you intend to use Pulse. Most databases utilize optimizations for these
types of queries and are, in my opinion, better practice than huge queries in your code base.

Pulse also provides both a simple Model class and an accompanying QueryBuilder, should you choose to use these. I will 
eventually write documentations for the functions it provides.

## Example Pulse Query:

    $con
      ->select(['thread_id', 'post_no], 'post_no')
      ->from('thread')
      ->where('date_deleted IS NULL AND board_id = ?')
      ->limit()
      ->offset()
      ->vars($boardID, 20, 1)
      ->query();

## Why Pulse?
- **Ease of Use**: Pulse uses a SQL-focused syntax, making it much easier to read what your query is doing without having to write large chunks of code or write "dynamic sql" / query-building functions.
- **Safety**: Every query goes through prepared statements, raw queries are not yet supported nor do I plan on supporintg these.
- **Less Boilerplate**: Pulse handles most boiler-plate for you, including the binding of parameters. This does mean you need to better validate your parameters types however.