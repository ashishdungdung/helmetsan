import os
import urllib.request
import re

scratchpad_file = "/Users/anumac/.gemini/antigravity/brain/3d4e3831-11b1-4c34-8c48-6f9e15e19bac/browser/scratchpad_qj5jfvlm.md"
output_dir = "/Users/anumac/Documents/ Projects/Helmetsan/helmet_images"

os.makedirs(output_dir, exist_ok=True)

with open(scratchpad_file, 'r') as f:
    content = f.read()

urls = re.findall(r'https://assets\.myntassets\.com/.*\.jpg', content)

for i, url in enumerate(urls):
    filename = os.path.join(output_dir, f"helmet_{i+1}.jpg")
    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
        with urllib.request.urlopen(req) as response, open(filename, 'wb') as out_file:
            data = response.read()
            out_file.write(data)
        print(f"Downloaded {filename}")
    except Exception as e:
        print(f"Failed {url}: {e}")
