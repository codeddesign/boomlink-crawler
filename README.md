boomlink
========

To create a project:
 Go to: http://107.170.94.168/phalcon_prj/html/public/crawler/tests/
 There you will have a form where you need to complete it with:
    * a bot name, e.g. boomlink-bot
    * number of links at once, e.g.: 10 (it's selectable)
    * a project's name, e.g.: 'project for wetter.de'
    * main url, e.g.: http://wetter.de OR wetter.de (it will add automatically http://)
 Important: by default, the depth of crawling is 99.

 'The alternative' needs also to add the above information with the exception of the 'main url'. The links added inside the 'textarea' need to be 1 per line.


To start the crawler:
 * php run.php
 * php run.php > /dev/null &
 * ! 2nd command is to run it in background

Crawler engine description and other details of the current working version:
$ run.php creates a service called 'ProjectListener' that his main purpose is to listen for new added projects.

$ ProjectListener by default it creates 2 sub-services:

- ProxyData - this service is using proxies in order to fetch information. It is running independent and it's getting the links from db that are not yet parsed. The status of this one is identified by column
'proxy_data_status' in table 'page_main_info' which will change to 1 when it's completed. The reason why this is runnig like this, because it's hard to manage the current number of proxies on parallel sub-services per each project
- PhantomData - this services makes http requests via curl to scripts/run_phantom.php, which executes a phantomjs script that it's loading a parsed link and it returns the page weight (in kb) and load time (in seconds).
The status of this service is identified by column 'phantom_data_status' in table 'page_main_info' which also will be 1 when it's completed. Right now, this script it's using a lot of resources. It's really hard to manage a lot
of requests at a time. So it is slow, because it's dependent on all resources that are loading with that link (ads, images, css files, ajax requests, rendering itself, etc.). prosieben.de for example works kind of slow.
In the future with a good server, this process might be recommended to be separated on a different server and run multiple concurrent requests per project. I recommend around 3-4 / project

! When a new project is being added the ProjectListener also creates 2 sub-services which will end/close right away after job is finished:
- One is to get the crawled page robots.txt file, called RobotsFile
- and another one called WhoIs which makes fsockopen requests to gather some information
^ this data will be added to status_domain

! Also, for each project, the ProjectListener creates 2 other sub-services:
- the actual crawler, called CrawlProject, which is getting the not yet parsed links and obviously it does the work. When a link is done parsed_status from page_main_info will change to 1
- another one called, ProxyData, which is getting the data from the 2 current api's (majestic and uclassify). When a link is completed api_data_status from page_main_info will change to 1
^ This 2 are running concurrently and independent of the other sub-services. This also means, that there are 2 services for each project, until the job is done.

Small case scenario, regarding the number of sub-process/threads-created:
- on start it will have 2# running services
- if is a new project, it will create 2# sub-services that will end right away
- as long as is work to do, it will have other 2# running subservices
So if a project is being added, it will always have 4 subthreads/subservices. If 2 projects are added, it will have (+2) 6 running sub-services, and so on ..