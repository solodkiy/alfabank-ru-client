"""
Basic skeleton of a mitmproxy addon.

Run as follows: mitmproxy -s anatomy.py
"""
from mitmproxy import ctx
import json


class Counter:
    def __init__(self):
        self.num = 0

    def request(self, flow):
        if flow.request.path == "/newclick-operations-history-ui/proxy/operations-history-api/operations":
            hstr = flow.request.headers.get('x-csrf-token')
            print('Token:' + hstr)

            hstr = flow.request.headers.get('Cookie')
            print('Cookie:' + hstr)


addons = [
    Counter()
]
