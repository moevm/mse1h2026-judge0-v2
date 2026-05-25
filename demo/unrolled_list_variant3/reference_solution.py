import sys

def solve(dataset):

    BLOCK_SIZE = 4
    blocks = []
    ans = ""

    def flatten():
        L = []
        for b in blocks:
            L.extend(b)
        return L

    def rebuild_dense(L):
        nonlocal blocks
        blocks = []
        i = 0
        while i < len(L):
            blocks.append(L[i:i+BLOCK_SIZE])
            i += BLOCK_SIZE

    def count():
        return sum(len(b) for b in blocks)

    for line in dataset.split("\n"):

        if not line:
            continue

        c = line.split()

        if c[0] == "end":
            break

        elif c[0] == "pushHead":
            L = flatten()
            L.insert(0, int(c[1]))
            rebuild_dense(L)

        elif c[0] == "pushTail":
            L = flatten()
            L.append(int(c[1]))
            rebuild_dense(L)

        elif c[0] == "push":
            idx = int(c[1])
            val = int(c[2])
            L = flatten()
            if idx < 0:
                idx = 0
            if idx > len(L):
                idx = len(L)
            L.insert(idx, val)
            rebuild_dense(L)

        elif c[0] == "pop":
            idx = int(c[1])
            L = flatten()
            if 0 <= idx < len(L):
                L.pop(idx)
            rebuild_dense(L)

        elif c[0] == "isEmpty":
            ans += str(int(count() == 0)) + "\n"

        elif c[0] == "count":
            ans += str(count()) + "\n"

        elif c[0] == "sort":
            L = sorted(flatten())
            rebuild_dense(L)

        elif c[0] == "removeDuplicates":
            seen = set()
            new = []
            for x in flatten():
                if x not in seen:
                    seen.add(x)
                    new.append(x)
            rebuild_dense(new)

        elif c[0] == "median":

            L = sorted(flatten())
        
            if not L:
                ans += f"{0.0:.1f}\n"
        
            else:
                n = len(L)
        
                if n % 2:
                    ans += f"{float(L[n//2]):.1f}\n"
                else:
                    ans += f"{((L[n//2-1] + L[n//2]) / 2.0):.1f}\n"

        elif c[0] == "reverseBlocks":
            blocks = blocks[::-1]

    ans += "List:\n"
    for x in flatten():
        ans += str(x) + "\n"

    return ans


if __name__ == '__main__':
    input_data = sys.stdin.read()
    print(solve(input_data), end='')
