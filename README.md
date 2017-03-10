# Kaktus
A quick-and-dirty task manager with websocket integration, designed for repetitive tasks.

Intent:

I'm sure you've had work that you have to do many times over. Things that would be prime for automation, if time and tools allowed for it. 
Instead, you are stuck making manual checklists each and everytime, or collaborating over awkward Excel spreadsheets.

Kaktus was designed to combat this by providing a simple, quick interface to create multiple tasks, pass them around, and clean them up.
There's nothing fancy here - simply the boards, the tasks, and the owners of said tasks. You can work alone or as part of a team.

Lots of work to do still to bring this to fruition - for example, task templates aren't in yet, and there's a dozen glaring holes to fix.
Still, I think it might be enjoyable for someone to use.

Uses Ratchet WebSockets, available here: http://socketo.me/

My test site's available here: https://www.drtorte.net/kaktus/

Setup:

Create a database for all appropriate fields. Host the site. Personally, I have set up HaProxy to enable me to host both the website and the websockets on the same
server. The files under src/kaktus are shared between the front and backend, so duplication is at least temporarily necessery.

Work to do:

Lots. Trello board here, for those so inclined: https://trello.com/b/yPgLnnxx/kaktus
