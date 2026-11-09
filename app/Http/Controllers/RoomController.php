<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function index(): View
    {
        return view('master-data.rooms.index', ['rooms' => Room::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('master-data.rooms.create', ['room' => new Room]);
    }

    public function store(Request $request): RedirectResponse
    {
        Room::create($this->validated($request));

        return Redirect::route('prodi.rooms.index')->with('status', 'Ruang berhasil ditambahkan.');
    }

    public function edit(Room $room): View
    {
        return view('master-data.rooms.edit', ['room' => $room]);
    }

    public function update(Request $request, Room $room): RedirectResponse
    {
        $room->update($this->validated($request, $room));

        return Redirect::route('prodi.rooms.index')->with('status', 'Ruang berhasil diperbarui.');
    }

    public function destroy(Room $room): RedirectResponse
    {
        if ($room->schedules()->exists()) {
            return Redirect::route('prodi.rooms.index')->with('error', 'Ruang sudah dipakai di jadwal ujian, nonaktifkan saja datanya.');
        }

        $room->delete();

        return Redirect::route('prodi.rooms.index')->with('status', 'Ruang berhasil dihapus.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', Rule::unique('rooms', 'code')->ignore($room)],
            'building' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1'],
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
