use Illuminate\Support\Str;

$room = new App\Models\Room();
$room->id = (string) Str::uuid();
$room->name = 'Presidential Luxury Suite';
$room->location = 'Jakarta Pusat';
$room->description = 'A brand new ultra-luxurious presidential suite.';
$room->facilities = json_encode(['Wi-Fi', 'AC', 'TV', 'Private Pool', 'City View']);
$room->price = 5000000;
$room->status = 'AVAILABLE';
$room->save();

echo "\n---NEW ROOM ID---\n";
echo $room->id;
echo "\n-----------------\n";
