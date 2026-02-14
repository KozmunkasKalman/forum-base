# Forum Base

Budget 4chan (imageboard without images and boards, not even threads)

Made with PHP+HTML+CSS+JS

No SQL, as simple read- and append-only CSV files are much safer

Text is automatically escaped, its perfectly safe from HTML/JS injection

This is a stripped down version of the forum page from my [website](https://bitpince.hu), that you can modify however you want

There is no license, do whatever you want with it



## Features:

- Password protection of usernames (``username&#password`` in the username field, based on the usernames.csv file)
- Username tags/capcodes (based on usernames.csv file, as well as one based on client, in this case Emacs users get purple name)
- Real time updating of posts
- Replies (clickable ids ``#1`` paste clickable reply links ``@1`` into text field)
- Text formatting, with provided help table in help page



## Database Format:

usernames.csv: name, password, role, optional ip
posts.csv: id, date (YYYY/MM/DD hh:mm:ss), username, text, ip, special post flag
