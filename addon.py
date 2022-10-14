"""
Basic skeleton of a mitmproxy addon.

Run as follows: mitmproxy -s anatomy.py
"""
from mitmproxy import ctx
import json
import time


class Counter:
    def __init__(self):
        self.num = 0

    def request(self, flow):
        log = open('log', 'a')
        log.write(flow.request.path + "\n")
        if flow.request.path == "/newclick-dashboard-ui/proxy/operations-history-api/operations":
            fp = open('out.json', 'w')
            #log.write('ZZZZ' + "\n")

            data = {
                "timestamp": time.time(),
                "token": flow.request.headers.get('x-csrf-token'),
                "cookie": flow.request.headers.get('Cookie')
            }

            fp.write(json.dumps(data))
            fp.close()


addons = [
    Counter()
]

