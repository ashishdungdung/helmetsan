import os
import json
import urllib.parse

def generate_amazon_link(brand, title, color="", size="", domain="amazon.com"):
    # Generate an Amazon search URL if we don't have a specific ASIN
    keywords = f"{brand} {title}"
    if color:
        keywords += f" {color}"
    if size:
        keywords += f" {size}"
    
    # URL encode the keywords
    query = urllib.parse.quote_plus(keywords)
    link = f"https://www.{domain}/s?k={query}"
    return link

def process_helmets(data_dir):
    helmets_dir = os.path.join(data_dir, 'helmets')
    updated_count = 0
    
    for filename in os.listdir(helmets_dir):
        if not filename.endswith('.json'):
            continue
            
        filepath = os.path.join(helmets_dir, filename)
        try:
            with open(filepath, 'r') as f:
                data = json.load(f)
        except json.JSONDecodeError:
            print(f"Error parsing: {filename}")
            continue
            
        # Get brand and title to formulate the root link
        brand = data.get('brand', '')
        title = data.get('title', '')
        
        # 1. Update root level marketplace_links
        if 'marketplace_links' not in data:
            data['marketplace_links'] = {}
            
        # If there's an existing ASIN, use it for Amazon US
        if 'affiliate' in data and 'amazon_asin' in data['affiliate']:
            asin = data['affiliate']['amazon_asin']
            if not asin.startswith("B0EXAMPLE"): # only use real ASINs
                 data['marketplace_links']['amazon_us'] = f"https://www.amazon.com/dp/{asin}"
            else:
                 data['marketplace_links']['amazon_us'] = generate_amazon_link(brand, title)
        else:
             data['marketplace_links']['amazon_us'] = generate_amazon_link(brand, title)
             
        data['marketplace_links']['amazon_uk'] = generate_amazon_link(brand, title, domain="amazon.co.uk")
        data['marketplace_links']['amazon_in'] = generate_amazon_link(brand, title, domain="amazon.in")
        data['marketplace_links']['amazon_de'] = generate_amazon_link(brand, title, domain="amazon.de")
        data['marketplace_links']['amazon_fr'] = generate_amazon_link(brand, title, domain="amazon.fr")
             
        # 2. Update variant level marketplace_links
        # Variants inherently have colors and sizes, making search URLs highly specific
        if 'variants' in data and isinstance(data['variants'], list):
            for variant in data['variants']:
                if 'marketplace_links' not in variant:
                    variant['marketplace_links'] = {}
                
                # Fetch variant specific info to make the search pinpoint accurate
                color = variant.get('color', variant.get('color_family', ''))
                size = variant.get('size', '')
                
                variant['marketplace_links']['amazon_us'] = generate_amazon_link(brand, title, color, size)
                variant['marketplace_links']['amazon_uk'] = generate_amazon_link(brand, title, color, size, domain="amazon.co.uk")
                variant['marketplace_links']['amazon_in'] = generate_amazon_link(brand, title, color, size, domain="amazon.in")
                variant['marketplace_links']['amazon_de'] = generate_amazon_link(brand, title, color, size, domain="amazon.de")
                variant['marketplace_links']['amazon_fr'] = generate_amazon_link(brand, title, color, size, domain="amazon.fr")
                
        # Write the updated JSON back out
        with open(filepath, 'w') as f:
            json.dump(data, f, indent=2)
            
        updated_count += 1
        
    print(f"Successfully processed and injected links into {updated_count} helmet JSONs.")

if __name__ == '__main__':
    data_dir = '/Users/anumac/Documents/Helmetsan/data'
    process_helmets(data_dir)
