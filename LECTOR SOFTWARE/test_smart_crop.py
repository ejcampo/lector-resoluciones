import os
from PIL import Image

base_dir = os.path.dirname(os.path.abspath(__file__))
pdf_dir = os.path.join(base_dir, "storage", "temp", "images")

def find_smart_crop_fraction(img_path):
    img = Image.open(img_path).convert('L')
    orig_width, orig_height = img.size
    
    # Scale down
    scale = 400 / orig_width
    height = int(orig_height * scale)
    img = img.resize((400, height))
    
    threshold = 200
    row_density = []
    for y in range(height):
        dark_pixels = sum(1 for x in range(0, 400, 2) if img.getpixel((x, y)) < threshold)
        row_density.append(dark_pixels)

    smoothed = []
    window = 3
    for i in range(height):
        start = max(0, i - window)
        end = min(height, i + window + 1)
        smoothed.append(sum(row_density[start:end]) / (end - start))

    img.close()

    # Find where the REAL content ends
    # We scan from bottom to top. We look for a block of content.
    # If we find a block, we check the gap ABOVE it. 
    # If the block is very low on the page AND separated by a massive gap from the rest of the text,
    # it is likely a scanner artifact border.
    
    # Let's find all content bounds:
    in_content = False
    content_spans = []
    start_y = 0
    for y in range(height):
        if smoothed[y] > 3:
            if not in_content:
                in_content = True
                start_y = y
        else:
            if in_content:
                in_content = False
                content_spans.append((start_y, y - 1))
    if in_content:
        content_spans.append((start_y, height - 1))
        
    if not content_spans:
        return 0.35

    # Filter out spans that are tiny (less than 2% of page)
    min_span = int(height * 0.02)
    valid_spans = [s for s in content_spans if (s[1] - s[0]) > min_span]
    
    if not valid_spans:
        return 0.35
        
    # We want to find the lowest valid span that is NOT isolated noise at the bottom.
    # If the last span is below 85% of the page AND there is a massive gap > 15% above it, drop it.
    while len(valid_spans) > 1:
        last_span = valid_spans[-1]
        prev_span = valid_spans[-2]
        gap = last_span[0] - prev_span[1]
        
        # If the gap is very large (> 15% of page) and the span is at the bottom (> 80%)
        if gap > int(height * 0.15) and last_span[0] > int(height * 0.80):
            valid_spans.pop() # Remove noise
        else:
            break
            
    real_content_bottom = valid_spans[-1][1]
    
    # Signature block is typically the last 25-30% of the document content.
    signature_height = int(height * 0.30)
    crop_start_y = max(0, real_content_bottom - signature_height)
    
    return crop_start_y / height

f1 = os.path.join(pdf_dir, "1783006959_6a4686efe6ac3_1783001289_6a4670c9b79c3_RS-05285-06-2026_pagina_7.jpg")
f2 = os.path.join(pdf_dir, "1783001695_6a46725feefee_RS-05286-06-2026_pagina_7.jpg")

print(f"05285 smart crop fraction: {find_smart_crop_fraction(f1):.3f}")
print(f"05286 smart crop fraction: {find_smart_crop_fraction(f2):.3f}")
