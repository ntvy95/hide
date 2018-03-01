Date of creation: Jul 03, 2016.
Last update: Jul 18, 2016.

# What is this?

A phpBB extension: HIDE BBCode. Based on [marcovo Hide BBcode](https://www.phpbb.com/community/viewtopic.php?t=2279486).

Tested on phpBB 3.1.9.

# Syntax

`[hide=<user 1 id>, <user 2 id> | <group 1 id>, <groupd 2 id>]The content only user 1, user 2 and members of group 1 or group 2 can view[/hide]`

# Example

`[hide]The content only the poster can view[/hide]`

`[hide=2]The content only user with id=2 can view[/hide]`

`[hide=2,3,5]The content only user with id=2 or id=3 or id=5 can view[/hide]`

`[hide=|2]The content only members of group with id=2 can view[/hide]`

`[hide=|2, 3, 5]The content only members of group with id=2 or id=3 or id=5 can view[/hide]`

`[hide=2, 3, 5|2, 3, 5]The content only  user with id=2 or id=3 or id=5 or members of group with id=2 or id=3 or id=5 can view[/hide]`

`[hide=2, 3, 5|2, 3, 5]The content only  user with id=2 or id=3 or id=5 or members of group with id=2 or id=3 or id=5 can view
[hide]The content only the poster can view[/hide][/hide]`

Poster always has the permission to view the hidden content in his / her own post. Users have the right to edit a post still can see the hidden content through editing the post.

The BBCode is still named "hide" for my own forum's purpose. If you want to change the BBCode tag name for compatibility with marcovo's work, just go ahead and edit [event/listener.php at lines 189 and 190](event/listener.php#L189).
