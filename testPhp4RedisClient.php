<?php
require_once 'Php4RedisClient.php';

$host = '127.0.0.1';
$port = 6379;

$redis = new Php4RedisClient($host, $port);

// Warning: This will flush the db!
//
$method = 'flushdb';
$args = array();

$redis->callRedis($method, $args);

// Add some data.
//
$flintstones = array(
        'Fred'      => "Interrogation of received notions was his on-going theme, and though the practice of making literary technique the unifying metaphor in a body of work tends to seal off poetry from an readership that could benefit from a skewed viewpoint—unlocking a door only to find another locked door, or a brick wall, ceases to be amusing once one begins to read poets for things other than status—Spicer rather positions the whole profession and the art as an item among a range of other activities individuals take on to make their dailylife cohere with a faint purpose they might feel welling inside them. Spicer, in matters of money, sexuality, poetry, religion zeros on the neatly paired arrangements our language system indexes our hairiest ideas with and sniffs a rat when the description opts for the easily deployed adjectives, similes and conclusions that make the hours go faster:",
        'Wilma'     => "There is a reservedly antagonistic undercurrent to Spicer's work, the subtle and ironic derision of the language arts that, as he sees them practiced, is locked up in petty matters of status, property, the ownership of ideas, the expansion of respective egos that mistake their basic cleverness for genius. The world, the external and physical realm that one cannot know but only describe with terms that continually need to be resuscitated, is, as we know, something else altogether that hasn't the need for elaborate vocabularies that compare Nature and Reality with everything a poet can get his or her hands on. What this proves, Spicer thinks (it seems to me, in any event) is that we know nothing of the material we try to distill in verse; even our language is parted out from other dialogues: ",
        'Barney'    => " Spicer is an interesting poet on several levels, all of them deep and rich with deposits that reward an earnest dig. He is, I think, on a par with Wallace Stevens and William Carlos Williams in grilling the elaborative infrastructure of how we draw or are drawn to specialized conclusions with the use of metaphor, and it is to his particular brilliance as a lyric poet, comparable to Frank O'Hara (a poet Spicer declared he didn't care for, with O'Hara thinking much the same in kind) that the contradictions, competing desires and unexpected conundrums of investigating one's verbal stream are made comprehensible to the senses, a joy to the ear. No one, really no one wrote as distinctly as the long obscure Spicer did, and editors Gizzi, Killian, and publisher Wesleyan Press are to be thanked for restoring a major American voice to our shared canon. ",
        'Jane'      => "In any anthology there are always some selections one likes more than others. There are always things left out that could have been included if space were unlimited. This necessity of exclusion and the limitation of space creates a challenge for any anthologist. A short poem is a similar kind of challenge. A lot more could be said, but we limit it to a brief presentation that aspires to have an enhanced impact by virtue of its very brevity. One would thus expect that authors who have immersed themselves in short poems would understand these challenges and limitations, and choose an anthology with great acuity and interest. Jonathan Greene and Robert West have delivered on that expectation.",
        'Bam-Bam'   => "",
        'Dino'      => "¥¥€"
);

$method = 'MSET';

foreach ($flintstones as $i => $id) {
    $args[] = 'flintstone:' . $i;
    $args[] = $id;
}

$redis->callRedis($method, $args);

// Get the example data.
//
$method = 'MGET';
$args = array();

foreach ($flintstones as $i => $id) {
    $args[] = 'flintstone:' . $i;
}

$redis->callRedis($method, $args);

print_r($redis->response);

$redis->disconnect();

exit();

