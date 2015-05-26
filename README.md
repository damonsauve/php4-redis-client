# php4-redis-client
A partially implemented Redis client for PHP4 partly based on Rediska.

In 2014, I worked on a website running legacy code that required access to a Redis data store. For obvious reasons, I was unable to find a Redis client library for PHP4. I considered downgrading the comprehensive Rediska, but I was constrained by time and effort. Instead, I wrote a stand-alone client which handled only the use cases needed to complete the task.

Perhaps this will be useful to someone.

See the test file for usage examples.
