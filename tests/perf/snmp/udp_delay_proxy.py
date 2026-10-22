#!/usr/bin/env python3
"""NMS-07-E4: UDP proxy in front of the simulated SNMP agent. Adds a fixed round-trip time to
every request (half each way) and counts the request datagrams, so the Go poller bench can
show how a poll scales with the network latency to a real OLT.

    udp_delay_proxy.py <listen_port> <agent_port> <rtt_ms> <count_file>
"""
import asyncio
import sys


class Proxy(asyncio.DatagramProtocol):
    def __init__(self, agent, rtt, count_file):
        self.agent, self.half, self.count_file, self.count = agent, rtt / 2000.0, count_file, 0
        self.upstream = {}

    def connection_made(self, transport):
        self.transport = transport
        with open(self.count_file, "w", encoding="ascii") as fh:
            fh.write("0")

    def datagram_received(self, data, client):
        self.count += 1
        with open(self.count_file, "w", encoding="ascii") as fh:
            fh.write(str(self.count))
        asyncio.get_running_loop().create_task(self.forward(data, client))

    async def forward(self, data, client):
        loop = asyncio.get_running_loop()
        if client not in self.upstream:
            proxy = self

            class Back(asyncio.DatagramProtocol):
                def datagram_received(self, reply, _addr):
                    loop.create_task(proxy.reply(reply, client))

            self.upstream[client], _ = await loop.create_datagram_endpoint(Back, remote_addr=self.agent)
        await asyncio.sleep(self.half)
        self.upstream[client].sendto(data)

    async def reply(self, data, client):
        await asyncio.sleep(self.half)
        self.transport.sendto(data, client)


async def main():
    listen, agent, rtt, count_file = int(sys.argv[1]), int(sys.argv[2]), float(sys.argv[3]), sys.argv[4]
    loop = asyncio.get_running_loop()
    await loop.create_datagram_endpoint(lambda: Proxy(("127.0.0.1", agent), rtt, count_file), local_addr=("127.0.0.1", listen))
    await asyncio.Event().wait()


if __name__ == "__main__":
    asyncio.run(main())
