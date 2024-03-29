if (!Date.prototype.toISOString) {
    Date.prototype.toISOString = function () {
        function pad(n) {
            return n < 10 ? '0' + n : n;
        }

        function ms(n) {
            return n < 10 ? '00' + n : n < 100 ? '0' + n : n
        }

        return this.getFullYear() + '-' +
        pad(this.getMonth() + 1) + '-' +
        pad(this.getDate()) + 'T' +
        pad(this.getHours()) + ':' +
        pad(this.getMinutes()) + ':' +
        pad(this.getSeconds()) + '.' +
        ms(this.getMilliseconds()) + 'Z';
    }
}

function createHAR(address, title, startTime, endTime, resources) {
    var entries = [], totalSize = 0;

    resources.forEach(function (resource) {
        var request = resource.request,
            startReply = resource.startReply,
            endReply = resource.endReply;

        if (!request || !startReply || !endReply) {
            return;
        }

        // Exclude Data URI from HAR file because
        // they aren't included in specification
        if (request.url.match(/(^data:image\/.*)/i)) {
            return;
        }

        // add total size:
        totalSize = totalSize + startReply.bodySize;

        entries.push({
            startedDateTime: request.time.toISOString(),
            endedDateTime: endTime.toISOString(),
            time: endReply.time - request.time,
            request: {
                method: request.method,
                url: request.url,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: request.headers,
                queryString: [],
                headersSize: -1,
                bodySize: -1
            },
            response: {
                status: endReply.status,
                statusText: endReply.statusText,
                httpVersion: "HTTP/1.1",
                cookies: [],
                headers: endReply.headers,
                redirectURL: "",
                headersSize: -1,
                bodySize: startReply.bodySize,
                content: {
                    size: startReply.bodySize,
                    mimeType: endReply.contentType
                }
            },
            cache: {},
            timings: {
                blocked: 0,
                dns: -1,
                connect: -1,
                send: 0,
                wait: startReply.time - request.time,
                receive: endReply.time - startReply.time,
                ssl: -1
            },
            pageref: address
        });
    });

    return {
        url: entries[0].request.url,
        duration: ((page.endTime - page.startTime)/1000).toFixed(2),
        size: (totalSize/1024).toFixed(2)
        /*log: {
            version: '1.2',
            creator: {
                name: "PhantomJS",
                version: phantom.version.major + '.' + phantom.version.minor +
                '.' + phantom.version.patch
            },
            pages: [{
                startedDateTime: startTime.toISOString(),
                id: address,
                title: title,
                pageTimings: {
                    onContentLoad: page.onContentLoad - page.startTime,
                    onLoad: page.endTime - page.startTime
                }
            }],
            entries: entries
        }*/
    };
}

var page = require('webpage').create(),
    system = require('system'),
    fs = require('fs');

// timeout handling http://stackoverflow.com/a/18837957/47573
page.settings.resourceTimeout = 10000; // 10 seconds
page.onResourceTimeout = function (e) {
    /*console.log("resourceTimeout triggered at " + e.url + " : code " + e.errorCode +
    " , error: " + e.errorString);
    phantom.exit(1);*/
};

function usage(msg) {
    console.log('Usage: netsniff.js [--timeout=10] [--output-json=file] <URL>');
    if (msg) {
        console.log('ERROR: ' + msg);
    }
    phantom.exit(1);
}

// quick&dirty option parsing
var url = null, outputHar = null, opt = 0;

for (var i in phantom.args) {
    var arg = phantom.args[i], m = null;

    if (arg.match(/^--/)) {
        if (m = arg.match(/^--timeout=(\d+)/)) {
            var num = parseInt(m[1], 10);
            if (!isNaN(num)) {
                page.settings.resourceTimeout = num * 1000;
            }
        } else if (m = arg.match(/^--output-json=(.*)/)) {
            outputHar = m[1];
        } else if (arg.match(/^--help$/)) {
            usage();
        } else {
            usage('Unknown option: ' + arg);
        }

        continue;
    }
    if (null === url) {
        url = arg;
        continue;
    }

    usage('Unknown option: ' + arg);
}

if (!url) {
    usage('An URL is required');
}

page.address = url;
page.resources = [];

page.onLoadStarted = function () {
    page.startTime = new Date();
};

page.onResourceRequested = function (req) {
    page.resources[req.id] = {
        request: req,
        startReply: null,
        endReply: null
    };
};

page.onResourceReceived = function (res) {
    if (res.stage === 'start') {
        page.resources[res.id].startReply = res;
    }
    if (res.stage === 'end') {
        page.resources[res.id].endReply = res;
    }
};

// https://gist.github.com/wangyang0123/2475509#comment-666382
page.onInitialized = function () {
    page.evaluate(function (domContentLoadedMsg) {
        document.addEventListener('DOMContentLoaded', function () {
            window.callPhantom('DOMContentLoaded');
        }, false);
    });
};

page.onCallback = function (data) {
    page.onContentLoad = new Date();
};

page.open(page.address, function (status) {
    var har, strHar;
    if (status !== 'success') {
        console.log('FAIL to load the address');
        /*phantom.exit(1);*/
    } else {
        page.endTime = new Date();
        page.title = page.evaluate(function () {
            return document.title;
        });

        har = createHAR(page.address, page.title, page.startTime, page.endTime, page.resources);
        strHar = JSON.stringify(har, undefined, 4);
        if (null === outputHar) {
            console.log('start-response:');
            console.log(strHar);
            console.log(':end-response');
        } else {
            try {
                fs.write(outputHar, strHar, 'w');
            } catch (e) {
                console.log(e);
                phantom.exit(1);
            }
        }
        phantom.exit();
    }
});