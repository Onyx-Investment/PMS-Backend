<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $clients = Client::with('contacts')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->orderBy('name')
            ->paginate(20);

        return response()->json($clients);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_code' => 'required|string|unique:clients,client_code',
            'name' => 'required|string|max:255',
            'sector' => 'nullable|string',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'status' => 'nullable|in:prospect,active,inactive',
        ]);

        $client = Client::create($data);

        return response()->json($client, 201);
    }

    public function show(Client $client)
    {
        return response()->json($client->load('contacts', 'projects'));
    }

    public function update(Request $request, Client $client)
    {
        $data = $request->validate([
            'client_code' => 'sometimes|string|unique:clients,client_code,' . $client->id,
            'name' => 'sometimes|string|max:255',
            'sector' => 'nullable|string',
            'address' => 'nullable|string',
            'website' => 'nullable|string',
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
            'status' => 'nullable|in:prospect,active,inactive',
        ]);

        $client->update($data);

        return response()->json($client);
    }

    public function destroy(Client $client)
    {
        $client->delete();

        return response()->json(['message' => 'Client deleted.']);
    }

    // Contact management methods
    public function contacts(Client $client)
    {
        return response()->json($client->contacts);
    }

    public function storeContact(Request $request, Client $client)
    {
        // Handle both single contact and bulk contacts
        if ($request->has('contacts')) {
            $data = $request->validate([
                'contacts' => 'required|array',
                'contacts.*.name' => 'required|string|max:255',
                'contacts.*.designation' => 'nullable|string|max:255',
                'contacts.*.phone' => 'nullable|string|max:255',
                'contacts.*.email' => 'nullable|email|max:255',
            ]);

            $contacts = [];
            foreach ($data['contacts'] as $contactData) {
                $contacts[] = $client->contacts()->create($contactData);
            }
            return response()->json($contacts, 201);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'designation' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = $client->contacts()->create($data);
        return response()->json($contact, 201);
    }

    public function updateContacts(Request $request, Client $client)
    {
        $data = $request->validate([
            'contacts' => 'required|array',
            'contacts.*.id' => 'nullable|exists:client_contacts,id',
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.designation' => 'nullable|string|max:255',
            'contacts.*.phone' => 'nullable|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
        ]);

        $existingIds = $client->contacts->pluck('id')->toArray();
        $updatedIds = [];

        foreach ($data['contacts'] as $contactData) {
            if (isset($contactData['id']) && in_array($contactData['id'], $existingIds)) {
                $contact = $client->contacts()->find($contactData['id']);
                if ($contact) {
                    $contact->update($contactData);
                    $updatedIds[] = $contactData['id'];
                }
            } else {
                $contact = $client->contacts()->create($contactData);
                $updatedIds[] = $contact->id;
            }
        }

        // Delete contacts that weren't included
        $toDelete = array_diff($existingIds, $updatedIds);
        if (!empty($toDelete)) {
            $client->contacts()->whereIn('id', $toDelete)->delete();
        }

        return response()->json($client->contacts);
    }

    public function updateContact(Request $request, Client $client, $contactId)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'designation' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = $client->contacts()->findOrFail($contactId);
        $contact->update($data);
        return response()->json($contact);
    }

    public function deleteContact(Client $client, $contactId)
    {
        $contact = $client->contacts()->findOrFail($contactId);
        $contact->delete();
        return response()->json(['message' => 'Contact deleted.']);
    }
}