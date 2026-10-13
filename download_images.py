import urllib.request
import urllib.parse
import os
import re

def download_image(query, filename):
    y_url = f"https://images.search.yahoo.com/search/images?p={urllib.parse.quote(query)}"
    req = urllib.request.Request(y_url, headers={"User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36"})
    try:
        html = urllib.request.urlopen(req).read().decode('utf-8')
        matches = re.findall(r"(?:src|data-src)=['\"](https?://[^'\"]+yimg\.com[^'\"]+)['\"]", html)
        for src in matches:
            if 'spaceball' not in src:
                print(f"Downloading {src} for {query}")
                urllib.request.urlretrieve(src, filename)
                return True
    except Exception as e:
        print(f"Error {query}: {e}")
    return False

queries = {
    "miss_dior.jpg": "Miss Dior Blooming Bouquet perfume",
    "coco_mademoiselle.jpg": "Chanel Coco Mademoiselle perfume",
    "jo_malone.jpg": "Jo Malone Wood Sage perfume",
    "tom_ford.jpg": "Tom Ford Oud Wood perfume",
    "versace_mini.jpg": "Versace Eros perfume bottle",
    "marc_jacobs_mini.jpg": "Marc Jacobs Daisy perfume",
    "ysl_libre.jpg": "YSL Libre perfume",
    "acqua_di_gio.jpg": "Giorgio Armani Acqua Di Gio perfume",
    "creed_aventus.jpg": "Creed Aventus perfume",
    "bvlgari_omnia.jpg": "Bvlgari Omnia Crystalline perfume",
    "gucci_bloom.jpg": "Gucci Bloom perfume",
    "lancome_lavieestbelle.jpg": "Lancome La Vie Est Belle perfume"
}

for fname, q in queries.items():
    filepath = os.path.join("uploads/products", fname)
    download_image(q, filepath)
